<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

// Define the plugin:
$PluginInfo['SphinxSearch'] = array(
   'Description' => 'Sphinx Search Plugin',
   'Version' => '0.1',
   'RegisterPermissions' => FALSE,
   'SettingsUrl' => '/garden/plugin/sphinxsearch',
   'SettingsPermission' => FALSE,
   'Author' => "Ben Lewis",
   'AuthorEmail' => 'ben@benandjo.co.uk',
   'AuthorUrl' => 'http://vanillaforums.org/profile/10945/benno'
);

// FUDGE: Attempt to overwrite the Garden SearchModel
$tmp = Gdn::FactoryOverwrite(TRUE);
Gdn::FactoryInstall('Gdn_SearchModel', 'SphinxModel', PATH_PLUGINS.DS.'SphinxSearch'.DS.'class.sphinxmodel.php', Gdn::FactorySingleton);
Gdn::FactoryInstall('SearchModel', 'SphinxModel', PATH_PLUGINS.DS.'SphinxSearch'.DS.'class.sphinxmodel.php', Gdn::FactorySingleton);
Gdn::FactoryOverwrite($tmp);
unset($tmp);

class Gdn_SphinxSearchPlugin implements Gdn_IPlugin {

  public function PluginController_SphinxSearch_Create(&$Sender) {
    $Validation = new Gdn_Validation();
    $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
    $ConfigurationModel->SetField(array('SphinxSearch.Host', 'SphinxSearch.Port', 'SphinxSearch.MaxResults'));
    
    $Sender->Form = Gdn::Factory('Form');
    $Sender->Form->SetModel($ConfigurationModel);
    
    if($Sender->Form->AuthenticatedPostBack() === FALSE) {
      $Sender->Form->SetData($ConfigurationModel->Data);
    } else {
      $ConfigurationModel->Validation->ApplyRule('SphinxSearch.Host', 'Required');
      $ConfigurationModel->Validation->ApplyRule('SphinxSearch.Port', 'Integer');
      $ConfigurationModel->Validation->ApplyRule('SphinxSearch.MaxResults', 'Integer');
      
      if ($Sender->Form->Save() !== FALSE) {
        $Sender->StatusMessage = Translate("Your settings have been saved.");
      }
    }
    
    $Sender->AddSideMenu(PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'settings.php');
    $Sender->AddJsFile('/plugins/SphinxSearch/search.js');
    $Sender->View = PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'settings.php';
    
		$Sender->Render();
  }

  public function SearchController_Index_Create(&$Sender, $Args = '') {
    $Offset = 0;
    $Limit = 0;
    
    if (array_key_exists(0, $Sender->RequestArgs) && is_numeric($Sender->RequestArgs[0]))
      $Offset = $Sender->RequestArgs[0];

    if (array_key_exists(1, $Sender->RequestArgs) && is_numeric($Sender->RequestArgs[1])) {
      $Limit = $Sender->RequestArgs[1];
    } else {
      $Limit = Gdn::Config('Garden.Search.PerPage', 20);
    }
    
    $Sender->View = PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'index.php';
    
    $Sender->AddJsFile('/js/library/jquery.gardenmorepager.js');
    $Sender->AddJsFile('/plugins/SphinxSearch/sphinxsearch.js');
    $Sender->AddCssFile('/plugins/SphinxSearch/sphinxsearch.css');
    
    $Sender->Title(Translate('Sphinx Search'));

    $Search = $Sender->Form->GetFormValue('Search');
    
    $Mode = $Sender->Form->GetFormValue('Mode');
    if ($Mode !== FALSE)
      $Sender->SearchModel->SetMatchMode($Mode);

    $CategoryFilter = $Sender->Form->GetFormValue('Category');
    if (is_array($CategoryFilter)) {
      foreach ($CategoryFilter as $Filter) {
        if ($Filter != '-1')
          $Sender->SearchModel->FilterPermissionJunction($Filter);
      }
    }
      
    $Sender->SetData('SearchOffset', $Offset, TRUE);
    $Sender->SetData('SearchTerm', Format::Text($Search), TRUE);
    
    if ($Search) {

      $ResultSet = $Sender->SearchModel->Search($Search, $Offset, $Limit);
      $Sender->SetData('SearchResults', $ResultSet, TRUE);
  
  		$NumResults = $ResultSet->NumRows();
  		if ($NumResults == $Offset + $Limit)
  			$NumResults++;
  	  
  	  $TotalFound = $ResultSet->TotalFound;

    } else {
      
      $NumResults = 0;
      $TotalFound = 0;
      
    }
  
		$PagerFactory = new PagerFactory();
		$Pager = $PagerFactory->GetPager('MorePager', $Sender);
		$Pager->MoreCode = 'More Results';
		$Pager->LessCode = 'Previous Results';
		$Pager->ClientID = 'Pager';
		$Pager->Configure(
			$Offset,
			$Limit,
			$TotalFound,
			'/search/%1$s/%2$s/?Search='.Format::Url($Search)
		);
		$Sender->SetData('Pager', $Pager, TRUE);
		
    if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
      $Sender->SetJson('LessRow', $Sender->Pager->ToString('less'));
      $Sender->SetJson('MoreRow', $Sender->Pager->ToString('more'));
      $Sender->View = PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'results.php';
    }
    
    $Sender->AddAsset('Panel','<a href="/search/advanced?Search='.urlencode($Search).'" class="AdvancedSearch">Advanced Search</a>');
    
    $Sender->Render();
  }
  
  public function SearchController_Advanced_Create(&$Sender, $Args = '') {
    $Session = Gdn::Session();
    $Sender->View = PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'advanced.php';
    $Sender->Title(Translate('Sphinx Search'));
    $Search = $Sender->Form->GetFormValue('Search');
    
    $searchmode = array('ANY' => 'Any Word', 'ALL' => 'All Words', 'PHRASE' => 'Exact Phrase');
    $Sender->SetData('SearchMode', $searchmode, TRUE);
    
    if (Gdn::Config('Vanilla.Categories.Use') === TRUE) {
      $categories = array('-1' => 'All Categories');
      $categoryds = $Sender->SearchModel->GetCategories();
      foreach($categoryds->Result() as $Data) {
        $categories[$Data->CategoryID] = $Data->Name;
      }
      $Sender->SetData('Categories', $categories, TRUE);
    }
        
    $Sender->Render();
  }

  public function Setup() {

    $Structure = Gdn::Structure();
    $Structure->Table('TableType')
      ->Column('MainIndexMaxID', 'int', 0)
      ->Set(FALSE, FALSE);
    
    SaveToConfig(array('SphinxSearch.Host'       => 'localhost', 
                       'SphinxSearch.Port'       => '9312', 
                       'SphinxSearch.MaxResults' => '1000'));
  }
  
}
