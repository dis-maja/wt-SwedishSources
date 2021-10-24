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

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Functions\FunctionsImport;
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
class SwedishSourcesParishAction implements RequestHandlerInterface
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

	$params = (array) $request->getParsedBody();

	$url = "";

	if ($params['btype'] != $params['obtype']) {
	    if ($btype == "0") {
		$url = route(SwedishSourceBtype::class,
			     ['tree' => $tree->name()]);
	    } elseif ($params['btype'] == "3") {
		$url = route(SwedishSourcesSubBtypePage::class,
			     ['tree' => $tree->name(),
			      'sbtype' => $params['btype']]);
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
	    if ($params['parish'] == "0") {
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

        return redirect($url);
    }
}
