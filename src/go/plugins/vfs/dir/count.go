/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package dir

import (
	"fmt"
	"io/fs"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"time"

	"zabbix.com/pkg/plugin"

	"zabbix.com/pkg/zbxerr"
)

const (
	emptyParam = iota
	firstParam
	secondParam
	thirdParam
	fourthParam
	fifthParam
	sixthParam
	seventhParam
	eightParam
	ninthParam
	tenthParam
	eleventhParam

	regularFile = 0

	unlimitedDepth = -1

	kilobyteType = 'K'
	megabyteType = 'M'
	gigabyteType = 'G'
	terabyteType = 'T'

	kb = 1000
	mb = kb * 1000
	gb = mb * 1000
	tb = gb * 1000

	secondsType = 's'
	minuteType  = 'm'
	hourType    = 'h'
	dayType     = 'd'
	weekType    = 'w'

	dayMultiplier  = 24
	weekMultiplier = 7
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type countParams struct {
	path          string
	minSize       string
	maxSize       string
	minAge        string
	maxAge        string
	maxDepth      int
	parsedMinSize int64
	parsedMaxSize int64
	parsedMinAge  time.Time
	parsedMaxAge  time.Time
	typesInclude  map[fs.FileMode]bool
	typesExclude  map[fs.FileMode]bool
	regExclude    *regexp.Regexp
	regInclude    *regexp.Regexp
	dirRegExclude *regexp.Regexp
}

var impl Plugin

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.dir.count":
		return p.exportCount(params)
	default:
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	return nil, nil
}

func (p *Plugin) exportCount(params []string) (result interface{}, err error) {
	cp, err := getParams(params)
	if err != nil {
		return
	}

	return cp.getDirCount()
}

func (cp *countParams) getDirCount() (int, error) {
	var count int

	length := len(strings.SplitAfter(cp.path, string(filepath.Separator)))

	err := cp.setMinMaxParams()
	if err != nil {
		return 0, zbxerr.ErrorInvalidParams.Wrap(err)
	}

	err = filepath.WalkDir(cp.path,
		func(p string, d fs.DirEntry, err error) error {
			s, err := cp.skip(p, length, d, err)
			if s {
				return err
			}

			count++

			return nil
		})

	if err != nil {
		return 0, zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	return count, nil
}

func (cp *countParams) skip(path string, length int, d fs.DirEntry, err error) (bool, error) {
	var s bool

	s, err = cp.skipPath(path, length, err)
	if s {
		return true, err
	}

	s, err = cp.skipRegex(d)
	if s {
		return true, err
	}

	s = cp.skipType(d)
	if s {
		return true, nil
	}

	s, err = cp.skipInfo(d)
	if s {
		return true, err
	}

	return false, nil
}

func (cp *countParams) skipPath(path string, length int, err error) (bool, error) {
	if err != nil {
		return true, err
	}

	if path == cp.path {
		return true, nil
	}

	currentLength := len(strings.SplitAfter(path, string(filepath.Separator)))
	if cp.maxDepth > unlimitedDepth && currentLength-length > cp.maxDepth {
		return true, fs.SkipDir
	}

	return false, nil
}

func (cp *countParams) skipRegex(d fs.DirEntry) (bool, error) {
	if cp.regInclude != nil && !cp.regInclude.Match([]byte(d.Name())) {
		return true, nil
	}

	if cp.regExclude != nil && cp.regExclude.Match([]byte(d.Name())) {
		return true, nil
	}

	if cp.dirRegExclude != nil && d.IsDir() && cp.dirRegExclude.Match([]byte(d.Name())) {
		return true, fs.SkipDir
	}

	return false, nil
}

func (cp *countParams) skipType(d fs.DirEntry) bool {
	if len(cp.typesInclude) > 0 && !isTypeMatch(cp.typesInclude, d.Type()) {
		return true
	}

	if len(cp.typesExclude) > 0 && isTypeMatch(cp.typesExclude, d.Type()) {
		return true
	}

	return false
}

func (cp *countParams) skipInfo(d fs.DirEntry) (bool, error) {
	i, err := d.Info()
	if err != nil {
		return true, err
	}

	if cp.minSize != "" {
		if cp.parsedMinSize > i.Size() {
			return true, nil
		}
	}

	if cp.maxSize != "" {
		if cp.parsedMaxSize < i.Size() {
			return true, nil
		}
	}

	if cp.minAge != "" && i.ModTime().After(cp.parsedMinAge) {
		return true, nil
	}

	if cp.maxAge != "" && i.ModTime().Before(cp.parsedMaxAge) {
		return true, nil
	}

	return false, nil
}

func (cp *countParams) setMinMaxParams() (err error) {
	err = cp.setMinParams()
	if err != nil {
		return
	}

	err = cp.setMaxParams()
	if err != nil {
		return
	}

	return
}

func (cp *countParams) setMaxParams() (err error) {
	if cp.maxSize != "" {
		cp.parsedMaxSize, err = parseByte(cp.maxSize)
		if err != nil {
			return
		}
	}

	if cp.maxAge != "" {
		var age time.Duration
		age, err = parseTime(cp.maxAge)
		if err != nil {
			return
		}

		cp.parsedMaxAge = time.Now().Add(-age)
	}

	return
}

func (cp *countParams) setMinParams() (err error) {
	if cp.minSize != "" {
		cp.parsedMinSize, err = parseByte(cp.minSize)
		if err != nil {
			err = zbxerr.ErrorInvalidParams.Wrap(err)

			return
		}
	}

	if cp.minAge != "" {
		var age time.Duration
		age, err = parseTime(cp.minAge)
		if err != nil {
			return
		}

		cp.parsedMinAge = time.Now().Add(-age)
	}

	return
}

func isTypeMatch(in map[fs.FileMode]bool, fm fs.FileMode) bool {
	if in[regularFile] && fm.IsRegular() {
		return true
	}

	if in[fm.Type()] {
		return true
	}

	return false
}

func getParams(params []string) (out countParams, err error) {
	out.maxDepth = -1

	switch len(params) {
	case eleventhParam:
		out.dirRegExclude, err = parseReg(params[10])
		if err != nil {
			err = zbxerr.New("Invalid eleventh parameter.").Wrap(err)

			return
		}

		fallthrough
	case tenthParam:
		out.maxAge = params[9]

		fallthrough
	case ninthParam:
		out.minAge = params[8]

		fallthrough
	case eightParam:
		out.maxSize = params[7]

		fallthrough
	case seventhParam:
		out.minSize = params[6]

		fallthrough
	case sixthParam:
		if params[5] != "" {
			out.maxDepth, err = strconv.Atoi(params[5])
			if err != nil {
				err = zbxerr.New("Invalid sixth parameter.").Wrap(err)

				return
			}
		}

		fallthrough
	case fifthParam:
		out.typesExclude, err = parseType(params[4], true)
		if err != nil {
			err = zbxerr.New("Invalid fifth parameter.").Wrap(err)

			return
		}

		fallthrough
	case fourthParam:
		out.typesInclude, err = parseType(params[3], false)
		if err != nil {
			err = zbxerr.New("Invalid fourth parameter.").Wrap(err)

			return
		}

		fallthrough
	case thirdParam:
		out.regExclude, err = parseReg(params[2])
		if err != nil {
			err = zbxerr.New("Invalid third parameter.").Wrap(err)

			return
		}

		fallthrough
	case secondParam:
		out.regInclude, err = parseReg(params[1])
		if err != nil {
			err = zbxerr.New("Invalid second parameter.").Wrap(err)

			return
		}

		fallthrough
	case firstParam:
		out.path = params[0]
		if out.path == "" {
			err = zbxerr.New("Invalid first parameter.")
		}

		if !strings.HasSuffix(out.path, string(filepath.Separator)) {
			out.path += string(filepath.Separator)
		}

	case emptyParam:
		err = zbxerr.ErrorTooFewParameters

		return
	default:
		err = zbxerr.ErrorTooManyParameters

		return
	}

	return
}

func parseReg(in string) (*regexp.Regexp, error) {
	if in == "" {
		return nil, nil
	}

	return regexp.Compile(in)
}

func parseType(in string, exclude bool) (out map[fs.FileMode]bool, err error) {
	if in == "" {
		return getEmptyType(exclude), nil
	}

	out = make(map[fs.FileMode]bool)
	types := strings.SplitAfter(in, ",")

	for _, t := range types {
		switch t {
		case "all":
			//If all are set no need to iterate further
			return getAllMode(), nil
		default:
			out, err = setIndividualType(out, t)
			if err != nil {
				return nil, err
			}
		}
	}

	return out, nil
}

func setIndividualType(m map[fs.FileMode]bool, t string) (map[fs.FileMode]bool, error) {
	switch t {
	case "file":
		m[regularFile] = true
	case "dir":
		m[fs.ModeDir] = true
	case "sym":
		m[fs.ModeSymlink] = true
	case "sock":
		m[fs.ModeSocket] = true
	case "bdev":
		m[fs.ModeDevice] = true
	case "cdev":
		m[fs.ModeDevice+fs.ModeCharDevice] = true
	case "fifo":
		m[fs.ModeNamedPipe] = true
	case "dev":
		m[fs.ModeDevice] = true
		m[fs.ModeDevice+fs.ModeCharDevice] = true
	default:
		return nil, fmt.Errorf("invalid type: %s", t)
	}

	return m, nil
}

func getEmptyType(exclude bool) map[fs.FileMode]bool {
	if exclude {
		return nil
	}

	return getAllMode()
}

func getAllMode() map[fs.FileMode]bool {
	out := make(map[fs.FileMode]bool)
	out[regularFile] = true
	out[fs.ModeDir] = true
	out[fs.ModeSymlink] = true
	out[fs.ModeSocket] = true
	out[fs.ModeDevice] = true
	out[fs.ModeCharDevice+fs.ModeDevice] = true
	out[fs.ModeNamedPipe] = true

	return out
}

func parseByte(in string) (int64, error) {
	if in == "" {
		return 0, nil
	}

	bytes, err := strconv.ParseInt(in, 10, 64)
	if err != nil {
		bytes, err := strconv.ParseInt(in[:len(in)-1], 10, 64)
		if err != nil {
			return 0, err
		}

		suffix := in[len(in)-1]
		switch suffix {
		case kilobyteType:
			return bytes * kb, nil
		case megabyteType:
			return bytes * mb, nil
		case gigabyteType:
			return bytes * gb, nil
		case terabyteType:
			return bytes * tb, nil
		default:
			return 0, fmt.Errorf("unknown memory suffix %s", string(suffix))
		}
	}

	return bytes, nil
}

func parseTime(in string) (time.Duration, error) {
	if in == "" {
		return 0 * time.Second, nil
	}

	t, err := strconv.ParseInt(in, 10, 64)
	if err != nil {
		t, err := strconv.ParseInt(in[:len(in)-1], 10, 64)
		if err != nil {
			return 0, err
		}

		suffix := in[len(in)-1]
		switch suffix {
		case secondsType:
			return time.Duration(t) * time.Second, nil
		case minuteType:
			return time.Duration(t) * time.Minute, nil
		case hourType:
			return time.Duration(t) * time.Hour, nil
		case dayType:
			return time.Duration(t) * time.Hour * dayMultiplier, nil
		case weekType:
			return time.Duration(t) * time.Hour * dayMultiplier * weekMultiplier, nil
		default:
			return 0, fmt.Errorf("unknown time suffix %s", string(suffix))
		}
	}

	return time.Duration(t) * time.Second, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "VFSDir",
		"vfs.dir.count", "Directory entry count.")
}
