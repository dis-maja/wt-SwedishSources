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

use Fig\Http\Message\StatusCodeInterface;
use DISMaja\Webtrees\Module\SwedishSources\SwedishSourcesModule;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesCountyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePage;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;

/**
 * Create a new Swedish source.
 */
class SwedishSourcesSubBtypePage implements RequestHandlerInterface
{
    use ViewResponseTrait;

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
	$sbtype = $request->getAttribute('sbtype');
	$subtype = $request->getAttribute('subtype');

	if ($subtype == "" AND isset($_REQUEST['subtype'])) {
	    $subtype = $_REQUEST['subtype'];
	}

	$cancel_url = route(TreePage::class, ['tree' => $tree->name()]);

	$params = (array) $request->getParsedBody();

	$tmp = explode('/',__DIR__);
	$name = '_' . $tmp[count($tmp)-2] . '_';

	$tmp = DB::table('swedish_sources')
		    ->where('gid', "=", $tree->id())
		    ->where('type', "=", 'BTYPE')
		    ->where('nothidden', "=", 1)
		    ->select(['rin', 'info'])
		    ->orderBy('rin', 'asc')
		    ->get();

	$booktype = array();
	foreach($tmp as $value) {
	    $booktype[$value->rin] = I18N::translate($value->info);
	}
	
	$user = $this->getPreference($name, 'USER','');
	$pass = $this->getPreference($name, 'PASS','');
	$url  = $this->getPreference($name, 'URL','');

	$tmp = json_decode($this->curlGet($url . '?do=getDBtypes', $user, $pass));

	$subtypes = [
	    0 => I18N::translate('&lt;select&gt;'),
	];	
	foreach($tmp as $value) {
	    $subtypes[$value->bdbDBcat] = I18N::translate($value->bdbDBname);
	}
	
	$books = [];
	if ($subtype != "" AND $subtype != "0") {

	    $tmp = (array) json_decode($this->curlGet($url . '?do=getDBtypes&type=' . $subtype, $user, $pass));

	    foreach($tmp as $value) {
		$books[$value->bdbDBid] = (array) $value;
	    }

	}

	return $this->viewResponse($name . '::add-db-book-source', [
	    'tree'	=> $tree,
            'title'     => I18N::translate('Create a Swedish source'),
	    'name'	=> $name,
	    'cancel'	=> $cancel_url,
	    'booktype'	=> $booktype,
	    'sbtype'	=> $sbtype,
	    'subtypes'	=> $subtypes,
	    'subtype'	=> $subtype,
	    'books'	=> $books,
	]);

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
