<?php

/**
 *
 * Copyright (c) 2021, Mats O Jansson <maja@dis-maja.se>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF
 * THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

declare(strict_types=1);

namespace DISMaja\Webtrees\Module\SwedishSources;

use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesBtypePage;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesBtypeAction;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesCountyPage;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesCountyAction;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesParishPage;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesParishAction;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesBooksPage;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesBooksAction;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesSubBtypePage;
use DISMaja\Webtrees\Module\SwedishSources\Http\SwedishSourcesSubBtypeAction;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Module\ModuleBlockTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use IntlChar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function assert;
use function redirect;
use function route;

class SwedishSourcesModule extends AbstractModule implements
	ModuleConfigInterface, ModuleCustomInterface, ModuleBlockInterface {

    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleBlockTrait;

    /** @var TreeService */
    private $tree_service;

    protected const ROUTE_URL = '/tree/{tree}/swedish-sources/{county}{/parish}';

    public const SCHEMA_VERSION = 1;

    private $migration_service;

    /** @var string[] Cached copy of the wt_gedcom_setting table. */
    private $preferences = [];

    public const BOOKTYPE_UNKNOWN = 0;
    public const BOOKTYPE_CHURCH_BOOKS = 1;
    public const BOOKTYPE_SCB = 2;
    public const BOOKTYPE_DBS_AND_BOOKS = 3;

    /**
     * Constructor.  The constructor is called on *all* modules, even ones that are disabled.
     * This is a good place to load business logic ("services").  Type-hint the parameters and
     * they will be injected automatically.
     */
    public function __construct(MigrationService $migration_service, TreeService $tree_service)
    {
        // NOTE:  If your module is dependent on any of the business logic ("services"),
        // then you would type-hint them in the constructor and let webtrees inject them
        // for you.  However, we can't use dependency injection on anonymous classes like
        // this one. For an example of this, see the example-server-configuration module.
	$this->migration_service = $migration_service;
	$this->tree_service = $tree_service;
    }

    /**
     * Bootstrap.  This function is called on *enabled* modules.
     * It is a good place to register routes and views.
     *
     * @return void
     */
    public function boot(): void
    {

	$router_container = app(RouterContainer::class);
	assert($router_container instanceof RouterContainer);
	$router = $router_container->getMap();

	$router->get(SwedishSourcesBtypePage::class,
			'/tree/{tree}/swedish-sources');
	$router->post(SwedishSourcesBtypeAction::class,
			'/tree/{tree}/swedish-sources');
	$router->get(SwedishSourcesCountyPage::class,
			'/tree/{tree}/swedish-sources/btype/{btype}');
	$router->post(SwedishSourcesCountyAction::class,
			'/tree/{tree}/swedish-sources/btype/{btype}');
	$router->get(SwedishSourcesSubBtypePage::class,
			'/tree/{tree}/swedish-sources/sbtype/{btype}');
	$router->post(SwedishSourcesSubBtypeAction::class,
			'/tree/{tree}/swedish-sources/sbtype/{btype}');
	$router->get(SwedishSourcesParishPage::class,
			'/tree/{tree}/swedish-sources/btype/{btype}/county/{county}');
	$router->post(SwedishSourcesParishAction::class,
			'/tree/{tree}/swedish-sources/btype/{btype}/county/{county}');
	$router->get(SwedishSourcesBooksPage::class,
			'/tree/{tree}/swedish-sources/btype/{btype}/county/{county}/parish/{parish}');
	$router->post(SwedishSourcesBooksAction::class,
			'/tree/{tree}/swedish-sources/btype/{btype}/county/{county}/parish/{parish}');

        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

	$this->migration_service
	    ->updateSchema('DISMaja\Webtrees\Module\SwedishSources\Schema',
			   'SWESRC_SCHEMA_VERSION', 1);
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Swedish Sources') . $this->langCodeStr(' SE');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('Add swedish source');
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Mats O Jansson';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return '2.0.5';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/dis-maja/wt-SwedishSources/main/latest-version.txt';
    }

    /**
     * Where to get support for this module.  Perhaps a github repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/dis-maja/wt-SwedishSources/issues';
    }

    public function getConfigLink(): string {
	return route('module', [
	    'module' => $this->name(),
	    'action' => 'Admin',
	]);
    }

    public function getAdminAction(ServerRequestInterface $request) : ResponseInterface {
	
	$tree_id = isset($_REQUEST['tree_id']) ? $_REQUEST['tree_id'] : '';
	$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : '';

	$params = (array) $request->getParsedBody();

	$tree = null;
	if (isset($params['tree_id'])) {
	    foreach($this->tree_service->all() as $t) {
		if ($t->id() == $tree_id) {
		    $tree = $t;
		}
	    }
	}

	$content = view($this->name() . '::config-admin', [
	    'title'	=> $this->title(),
	    'module'	=> $this->name(),
	    'tree'	=> $tree,
	    'trees'	=> $this->tree_service->all(),
	    'admin'	=> true,
	    'tab'	=> $tab,
	]);

	$html = View::make('layouts/administration', [
	    'title'	=> $this->title(),
	    'content'	=> $content,
	]);

	return response($html);

    }

    public function postAdminAction(ServerRequestInterface $request) : ResponseInterface {

	$params = (array) $request->getParsedBody();

	$foo = (array) $request->getQueryParams();

	$tree_id = isset($_REQUEST['tree_id']) ? $_REQUEST['tree_id'] : '';
	$tab	 = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : '';
	$save	 = isset($_REQUEST['save']) ? $_REQUEST['save'] : '';

	if ($tab == '') {
	    return redirect(route('module', [
				   'module' => $this->name(),
				   'action' => 'Admin',
				   'tree_id' => $tree_id,
				   ]));
	}

	if ($tab == '1' AND $save == '1') {
	    // Update Repositotory
	    if (isset($params['repo'])) {
		foreach($params['repo'] as $key => $value) {
		    $this->addREPO($params['tree'], $value);
		}
	    }

	    return redirect(route('module', [
				   'module' => $this->name(),
				   'action' => 'Admin',
				   'tree_id' => $params['tree'],
				   'tab' => $params['tab'],
				   ]));
	}

	if ($tab == '2' AND $save == '1') {
	    // Update County
	    if (isset($params['county'])) {
		foreach($params['county'] as $key => $value) {
		    $this->addCOUNTY($params['tree'], $value);
		}
	    }

	    return redirect(route('module', [
				   'module' => $this->name(),
				   'action' => 'Admin',
				   'tree_id' => $params['tree'],
				   'tab' => $params['tab'],
				   ]));
	}

	if ($tab == '3' AND $save == '1') {
	    // Update Book type
	    if (isset($params['btype'])) {
		foreach($params['btype'] as $key => $value) {
		    $this->addBTYPE($params['tree'], (string) $key, $value);
		}
	    }
	    if (isset($params['show'])) {
		foreach($params['show'] as $key => $value) {
		    $this->updateBTYPE($params['tree'], (string) $value);
		}
	    }

	    return redirect(route('module', [
				   'module' => $this->name(),
				   'action' => 'Admin',
				   'tree_id' => $params['tree'],
				   'tab' => $params['tab'],
				   ]));
	}

	if ($tab == '4' AND $save == '1') {
	    // Update Webservice
	    if ($_REQUEST['user'] != $_REQUEST['ouser']) {
		$this->setPref($params['tree'], 'USER', $_REQUEST['user']);
	    }
	    if ($_REQUEST['pass'] != $_REQUEST['opass']) {
		$this->setPref($params['tree'], 'PASS', $_REQUEST['pass']);
	    }
	    if ($_REQUEST['url'] != $_REQUEST['ourl']) {
		$this->setPref($params['tree'], 'URL', $_REQUEST['url']);
	    }
	    return redirect(route('module', [
				   'module' => $this->name(),
				   'action' => 'Admin',
				   'tree_id' => $tree_id,
				   'tab' => '4',
				   ]));
	}

	return redirect(route('module', [
				'module' => $this->name(),
				'action' => 'Admin']));
    }

    public function setPref(string $tree_id, string $setting_name,
			    string $setting_value): void
    {
	if ($setting_value !== $this->getPref($tree_id, $setting_name)) {
	    DB::table('swedish_sources')->updateOrInsert([
		'gid'		=> $tree_id,
		'type'		=> $setting_name,
	    ],[
		'rin'		=> '',
		'nothidden'	=> 1,
		'info'		=> $setting_value,
	    ]);
	}
    }

    public function getPref(string $tree_id, string $setting_name,
			    string $default = ''): string
    {
	$preferences = DB::table('swedish_sources')
	    ->where('gid', '=', $tree_id)
	    ->pluck('info', 'type')
	    ->all();

	return $preferences[$setting_name] ?? $default;
    }

    private function addREPO(string $tree_id, string $rin): void
    {

	$user = $this->getPref($tree_id,'USER','');
	$pass = $this->getPref($tree_id,'PASS','');
	$url  = $this->getPref($tree_id,'URL','');

	$tmpTree = new Tree((int) $tree_id, '', '');
	
	$param = $url . "?do=getRepository&RIN=" . $rin;
	$x = $this->curlGet($param, $user, $pass);
	$repo = json_decode($x);
	
	if (count((array) $repo) > 0) {

	    $name = '';
	    $new[] = "0 @@ REPO";
	    foreach($repo as $r) {

		if ($r->bdbRIrow == 1) {
		    $tmp = $r->bdbRIinfo;
		    if ($r->bdbRItype == 'NAME') { $name = $tmp; }
		    if ($r->bdbRItype == "PHON" OR
		        $r->bdbRItype == "FAX") {
			$tmp = str_replace("+46-(0)","0",$r->bdbRIinfo);
		    }
		    $new[] = "1 " . $r->bdbRItype . " " . $tmp;
		} else {
		    $tmp = $r->bdbRIinfo;
		    if ($r->bdbRItype == "ADDR") {
		        $tmp = str_replace("SE-","",$r->bdbRIinfo);
		    }
		    $new[]= "2 CONT " . $tmp;
		}

	    }

	    $gedcom = implode(chr(10),$new);
	    $record = $tmpTree->createRecord($gedcom);
	    $record = Registry::repositoryFactory()->new($record->xref(), $record->gedcom(), null, $tmpTree);

	    $str = I18N::translate('Created the repository "%s" as %s', $name, $record->xref());
	    FlashMessages::addMessage($str, 'success');

	}
    }

    private function addCOUNTY(string $tree_id, string $rin): void
    {

	$user = $this->getPref($tree_id,'USER','');
	$pass = $this->getPref($tree_id,'PASS','');
	$url  = $this->getPref($tree_id,'URL','');

#	$tmpTree = new Tree((int) $tree_id, '', '');
	
	$param = $url . "?do=getCounty&RIN=" . $rin;
	$x = $this->curlGet($param, $user, $pass);
	$county = json_decode($x);
	
	if (count((array) $county) > 0) {

	    $y = DB::table('swedish_sources')
		      ->insert([ 'gid' => $tree_id,
				 'type' => 'COUNTY',
				 'rin' => $county->bdbCTid,
				 'nothidden' => $county->bdbCTok,
				 'info' => $county->bdbCTname ]);

	    $str = I18N::translate('Created the county "%s" as %s', $county->bdbCTname, $county->bdbCTid);
	    FlashMessages::addMessage($str, 'success');

	}
    }
    

    private function addBTYPE(string $tree_id, string $rin, string $info): void
    {

	$user = $this->getPref($tree_id,'USER','');
	$pass = $this->getPref($tree_id,'PASS','');
	$url  = $this->getPref($tree_id,'URL','');

	$y = DB::table('swedish_sources')
		  ->insert([ 'gid' => $tree_id,
			     'type' => 'BTYPE',
			     'rin' => $rin,
			     'nothidden' => 0,
			     'info' => $info ]);

	$str = I18N::translate('Created the booktype "%s" as %s',
				I18N::translate($info), $rin);
	FlashMessages::addMessage($str, 'success');
    }

    private function updateBTYPE(string $tree_id, string $id): void
    {

	$user = $this->getPref($tree_id,'USER','');
	$pass = $this->getPref($tree_id,'PASS','');
	$url  = $this->getPref($tree_id,'URL','');

	$y = DB::table('swedish_sources')
		  ->where('gid', "=", $tree_id)
		  ->where('type', "=", 'BTYPE')
		  ->where('id', "=", $id)
		  ->select('nothidden')
		  ->get();

	$nothidden = ($y[0]->nothidden) ? 0 : 1;

	DB::table('swedish_sources')
	     ->where('id', "=", $id)
	     ->update([
		'nothidden' => $nothidden,
	     ]);

#$str = I18N::translate('Created booktype %s as %s', $info, $rin);
#	FlashMessages::addMessage($str, 'success');
    }

    /*
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return array<string>
     */
    public function customTranslations(string $language): array
    {
        switch ($language) {
	    case 'sv':
		return $this->swedishTranslations();

            default:
                return [];
        }
    }

    /**
     * @return array<string,string>
     */
    protected function swedishTranslations(): array
    {
	return [
	    'Add book type' => 'Lägg till boktyp',
	    'Add county' => 'Lägg till län',
	    'Add repository' => 'Lägg till arkiv',
	    'Add swedish source' => 'Lägg till svensk källa',
	    'Book type' => 'Boktyp',
	    'bookDB' => 'bookDB',
	    'Change' => 'Ändra',
	    'Church books' => 'Kyrkböcker',
	    'County' => 'Län',
	    'Create a Swedish source' => 'Skapa en svensk källa',
	    'Created the county "%s" as %s' => 'Skapade länet "%s" som %s',
	    'Created the repository "%s" as %s' => 'Skapade arkivet "%s" som %s',
	    'Created the source "%s" as %s' => 'Skapade källan "%s" som %s',
	    'Databases and books' => 'Databaser och böcker',
	    'Encyclopedias' => 'Uppslagsverk',
	    'Herdaminnen' => 'Herdaminnen',
	    'Id (in %s)' => 'Id (i %s)',
	    'Local databases' => 'Lokala databaser',
	    'Online databases' => 'Online databaser',
	    'Parish' => 'Församling',
	    'SCB Extracts' => 'SCB utdrag',
	    'SCB Extracts from Church Books 1860-1949' =>
	        'SCB utdrag från kyrkböcker 1860-1949',	
	    'Subtype' => 'Undertyp',
	    'Swedish Sources' => 'Svenska källor',

	];
    }

    /**
     * Generate the HTML content of this block.
     *
     * @param Tree	$tree
     * @param int	$block_id
     * @param string	$context
     * @param string[]	$config
     *
     * @return string
     */
    public function getBlock(Tree $tree, int $block_id, string $context, array $config = []): string
    {

	$url = route(SwedishSourcesBtypePage::class, ['tree' => $tree->name()]);

	$content  = '<a class="btn btn-primary" href="' . $url . '">';
	$content .= I18N::translate('Create a Swedish source');
	$content .= '</a>';

	if ($context !== self::CONTEXT_EMBED) {
	    return view('modules/block-template', [
		'block'		=> Str::kebab($this->name()),
		'id'		=> $block_id,
		'config_url'	=> '', # $this->configUrl($tree, $context, $block_id),
		'title'		=> $this->title(),
		'content'	=> $content,
	    ]);
	}

	return route(SwedishSourcesPage::class, ['tree' => $tree->name()]);

    }

    /**
     * Should this block load asynchronously using AJAX?
     *
     * Simple blocks are faster in-line, more complex ones can be loaded later.
     *
     * @return bool
     */
    public function loadAjax(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the user’s home page?
     *
     * @return bool
     */
    public function isUserBlock(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the tree’s home page?
     *
     * @return bool
     */
    public function isTreeBlock(): bool
    {
        return true;
    }

    public function editBlockConfiguration(Tree $tree, int $block_id): string
    {

	$content = view($this->name() . '::config-admin', [
	    'title'	=> $this->title(),
	    'module'	=> $this->name(),
	    'tree'	=> $tree,
	    'trees'	=> $this->tree_service->all(),
	    'admin'	=> false,
	    'tab'	=> '',
	]);

	return $content;
    }
    
    public function saveBlockConfiguration($request, $block_id): void
    {
        $params = (array) $request->getParsedBody();

	$tree = $params['tree'];

	if (isset($params['manager']) &&
	    $params['manager'] == 1) {

	    if ($params['user'] != $params['ouser']) {
		$this->setPreference($tree,'USER', $params['user']);
	    }

	    if ($params['pass'] != $params['opass']) {
		$this->setPreference($tree,'PASS', $params['pass']);
	    }

	    if ($params['url'] != $params['ourl']) {
		$this->setPreference($tree,'URL', $params['url']);
	    }

	}

	$user = $this->getPref($tree,'USER','');
	$pass = $this->getPref($tree,'PASS','');
	$url  = $this->getPref($tree,'URL','');

	if (isset($params['county']) AND
	    is_array($params['county'])) {

	    foreach($params['county'] as $k => $v) {

	        $param = $url . "?do=getCounty&RIN=" . $v;
		$county = json_decode($this->curlGet($param, $user, $pass));

		$y = DB::table('swedish_sources')
			 ->insert([
			     'gid' => $tree,
			     'type' => 'COUNTY',
			     'rin' => $county->bdbCTid,
			     'nothidden' => $county->bdbCTok,
			     'info' => $county->bdbCTname,
			   ]);

	    }
	}

	if (isset($params['repo']) AND
	    is_array($params['repo'])) {

	    $tmpTree = new Tree((int) $params['tree'],
				$params['name'],
				$params['title']);

	    foreach($params['repo'] as $k => $v) {

	        $param = $url . "?do=getRepository&RIN=" . $v;
		$x = $this->curlGet($param, $user, $pass);
		$repo = json_decode($x);

		if (count((array) $repo) > 0) {

		    $new[] = "0 @@ REPO";
		    foreach($repo as $r) {

			if ($r->bdbRIrow == 1) {
			    $tmp = $r->bdbRIinfo;
			    if ($r->bdbRItype == "PHON" OR
			        $r->bdbRItype == "FAX") {
				$tmp = str_replace("+46-(0)","0",$r->bdbRIinfo);
			    }
			    $new[] = "1 " . $r->bdbRItype . " " . $tmp;
			} else {
			    $tmp = $r->bdbRIinfo;
			    if ($r->bdbRItype == "ADDR") {
			        $tmp = str_replace("SE-","",$r->bdbRIinfo);
			    }
			    $new[]= "2 CONT " . $tmp;
			}

		    }

		    $gedcom = implode(chr(10),$new);
		    $record = $tmpTree->createRecord($gedcom);
		    $record = Registry::repositoryFactory()->new($record->xref(), $record->gedcom(), null, $tmpTree);

		}

	    }
	}

	return;
    }

    /**
     * @return array<int,string>
     */
    public function getBookTypes(): array
    {
	return DB::table('swedish_sources')
		    ->where('gid', "=", $tree->id())
		    ->where('type', "=", 'BTYPE')
		    ->where('nothidden', "=", 1)
		    ->pluck('info', 'run');
    }

    /**
     * @return array<int,string>
     */
    public function getCounties(Tree $tree): array
    {
	return DB::table('swedish_sources')
		    ->where('gid', "=", $tree->id())
		    ->where('type', "=", 'COUNTY')
		    ->pluck('info', 'rin');
    }

    /**
     * @return string
     */
    public function getModuleName(): string
    {
	$tmp = explode('/',__DIR__);
	return '_' . $tmp[count($tmp)-2] . '_';
    }

    /**
     * @return string
     */
    private function langCodeStr($lang): string
    {
	$r = '';
	for ($i=0; $i<strlen($lang); $i++)
	{
	    $c = $lang[$i];
	    switch($c) {
		case ' ':
		     $r .= $c;
		     break;
		case in_array($c, range('A','Z')):
		     $r .= IntlChar::chr(0x1f1e6 + ord($c) - ord('A'));
		     break;
		default:
	    }
	}
	return $r;
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

};
