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
class SwedishSourcesCountyPage implements RequestHandlerInterface
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

	$cancel_url = route(SwedishSourcesBtypePage::class, [
			    'tree' => $tree->name()]);
	$cancel_url = str_replace('%2Fswedish-sources', '', $cancel_url);

	$tmp = explode('/',__DIR__);
	$name = '_' . $tmp[count($tmp)-2] . '_';

	$tmp = DB::table('swedish_sources')
		    ->where('gid', "=", $tree->id())
		    ->where('type', "=", 'BTYPE')
		    ->where('nothidden', "=", 1)
		    ->select(['rin', 'info'])
		    ->get();

	$booktype = array();
	foreach($tmp as $value) {
	    $booktype[$value->rin] = I18N::translate($value->info);
	}
	
	$tmp = DB::table('swedish_sources')
		    ->where('gid', "=", $tree->id())
		    ->where('type', "=", 'COUNTY')
		    ->where('nothidden', "=", 1)
		    ->select(['rin', 'info'])
		    ->get();

	$counties = [
	    0 => I18N::translate('Choose county'),
	];	
	foreach($tmp as $value) {
	    $counties[$value->rin] = $value->info;
	}

	return $this->viewResponse($name . '::add-swedish-source', [
	    'tree'	=> $tree,
            'title'     => I18N::translate('Create a Swedish source'),
	    'name'	=> $name,
	    'cancel'	=> $cancel_url,
	    'booktype'	=> $booktype,
	    'btype'	=> $btype,
	    'counties'	=> $counties,
	]);

    }
}
