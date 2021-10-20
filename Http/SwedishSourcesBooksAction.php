<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2021 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace DISMaja\Webtrees\Module\SwedishSources\Http;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function preg_match_all;
use function redirect;
use function route;

/**
 * Create a new Swedish source.
 */
class SwedishSourcesBooksAction implements RequestHandlerInterface
{

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
	$tree = $request->getAttribute('tree');
	assert($tree instanceof Tree);

	$btype = $request->getAttribute('btype');
	$county = $request->getAttribute('county');
	$parish = $request->getAttribute('parish');

	$params = (array) $request->getParsedBody();

	$tmp = explode('/',__DIR__);
	$name = '_' . $tmp[count($tmp)-2] . '_';

	$repo_from = [ '@XREF@' ];
	$repo_to = [ '@@' ];

	$y = DB::table('other')
		  ->where('o_file', '=', $tree->id())
		  ->where('o_type', '=', 'REPO')
		  ->select('o_id', 'o_gedcom')
		  ->get();
	foreach($y as $val) {
	    $tmp = explode(chr(10), $val->o_gedcom);
	    $i = -1;
	    foreach($tmp as $k => $v) {
		if (substr($v,0,6) == '1 RIN ') {
		    $i = $k;
		}
	    }
	    $repo_from[] = '@' . trim(str_replace('1 RIN ','', $tmp[$i])) . '@';
	    $repo_to[] = '@' . trim($val->o_id) . '@';
	}

	$url = "";

	if ($params['btype'] != $params['obtype']) {
	    if ($btype == "0") {
		$url = route(SwedishSourcesBtypePage::class,
			     ['tree' => $tree->name()]);
	    } else {
		$url = route(SwedishSourcesCountyPage::class,
			     ['tree' => $tree->name(),
			      'btype' => $params['btype']]);
	    }
	}

	if ($url == "") {
	    if ($params['county'] != $params['ocounty']) {
		if ($county == "0") {
		    $url = route(SwedishSourcesCountyPage::class,
				 ['tree' => $tree->name(),
				  'btype' => $btype]);
		} else {
		    $url = route(SwedishSourcesParishPage::class,
				 ['tree' => $tree->name(),
				  'btype' => $btype,
				  'county' => $params['county']]);
		}
	    }
	}
	
	if ($url == "") {
	    if ($params['parish'] != $params['oparish']) {
		if ($parish == "0") {
		    $url = route(SwedishSourcesParishPage::class,
				 ['tree' => $tree->name(),
				  'btype' => $btype,
				  'county' => $county]);
		} else {
		    $url = route(SwedishSourcesBooksPage::class,
				 ['tree' => $tree->name(),
				  'btype' => $btype,
				  'county' => $county,
				  'parish' => $params['parish']]);
		}
	    }
	}
	
	if ($url == "") {

	    $user = $this->getPreference($name, 'USER','');
	    $pass = $this->getPreference($name, 'PASS','');
	    $xurl  = $this->getPreference($name, 'URL','');

	    if (isset($params['sour']) AND is_array($params['sour'])) {
		foreach($params['sour'] as $val) {
		    $src = $this->curlGet($xurl . '?do=gcSOUR&RIN=' . $val, $user, $pass);
		    $src = str_replace($repo_from, $repo_to, $src);

		    $rest = json_decode($src);
		    $tmp = [];
		    // Handle SOUR
		    foreach($rest as $k => $v) {
			if ($v[0] == 0) {
			    $v[1] = $v[1] . '\n';
#			    $v[1] = trim($v[1]);
			    $tmp[] = implode(' ',$v);
			    unset($rest[$k]);
			}
		    }
		    foreach(array('TITL','ABBR','AUTH') as $part) {
			foreach($rest as $k => $v) {
			    if ($v[0] == 1 AND substr($v[1],0,4) == $part) {
				$tmp[] = implode(' ',$v);
				unset($rest[$k]);
			    }
			}
		    }
		    foreach($rest as $k => $v) {
			$tmp[] = implode(' ',$v);
		    }
		    $gedcom = implode(chr(10), $tmp);

		    $record = $tree->createRecord($gedcom);
		    $record = Registry::sourceFactory()->new($record->xref(), $record->gedcom(), null, $tree);

		}
		$url = route(SwedishSourcesBooksPage::class,
			     ['tree' => $tree->name(),
			      'btype' => $btype,
			      'county' => $county,
			      'parish' => $params['parish']]);
	    }
	}

#	if ($url == "" AND !isset($params['sour']) {
#	    $url = route(SwedishSourcesBtypePage::class,
#		       ['tree' => $tree->name()]);
#	}			     

        return redirect($url);
    }

    private function curlGet($url, $user = NULL, $pass = NULL): string {

	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url) or die(curl_error());
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1) or die(curl_error());
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout) or die(curl_error());
	if ($user != NULL AND $pass != NULL) {
	    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) or die(curl_error());
	    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass) or die(curl_error());
	}
	$data = curl_exec($ch) or die(curl_error($ch)) or die(curl_error());;
	curl_close($ch);
	return $data;

    }

    private function getPreference(string $name, string $setting_name, string $default = ''): string
    {
	return DB::table('module_setting')
	    ->where('module_name', '=', $name)
	    ->where('setting_name', '=', $setting_name)
	    ->value('setting_value') ?? $default;
    }
}

