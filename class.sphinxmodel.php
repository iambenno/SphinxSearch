<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

class SphinxModel extends Gdn_SearchModel {

  protected $_SphinxClient = null;
  protected $_JunctionFilters;
  protected $_MatchMode;
  protected $_Indexes;

  function __construct() {
    if (!class_exists("SphinxClient")) {
      require_once(PATH_LIBRARY.DS.'vendors'.DS.'sphinx'.DS.'sphinxapi.php');
    }
    $this->_SphinxClient = new SphinxClient();
    $this->_SphinxClient->SetServer(Gdn::Config('SphinxSearch.Host'), (int)Gdn::Config('SphinxSearch.Port'));
    $this->_SphinxClient->SetFieldWeights(array("Title" => 5, "Body" => 1));
    $this->_SphinxClient->SetConnectTimeout(1);
    $this->_SphinxClient->SetFilter("deleted", array(0));
    
    $this->_MatchMode = SPH_MATCH_ANY;
    $this->_JunctionFilters = array();
    $this->_Indexes = 'index_garden_search_main;index_garden_search_delta';
    parent::__construct();
  }
  
  public function GetCategories() {
    return $this->SQL
      ->Select('c.CategoryID, c.Name')
      ->From('Category c')
      ->Permission('c', 'CategoryID', 'Vanilla.Discussions.View')
      ->Get();
  }
  
  public function FilterPermissionJunction($Id) {
    if (is_numeric($Id)) {
      $this->_JunctionFilters[] = $Id;
    }
  }

  public function SetMatchMode($Mode) {
    switch ($Mode) {
      case 'ALL':
        $this->_MatchMode = SPH_MATCH_ALL;
        break;
      case 'PHRASE':
        $this->_MatchMode = SPH_MATCH_PHRASE;
        break;
      default:
        $this->_MatchMode = SPH_MATCH_ANY;
    }
  }

  public function Search($Search, $Offset = 0, $Limit = 20) {
    $MaxResults = Gdn::Config('SphinxSearch.MaxResults', 1000);

    $this->_SphinxClient->SetLimits((int)$Offset, (int)$Limit, (int)$MaxResults);
    $this->_SphinxClient->SetMatchMode($this->_MatchMode);
    
    // Restrict to visible categories only
    $this->SQL
         ->Select('c.CategoryID')
         ->From('Category c')
         ->Permission('c', 'CategoryID', 'Vanilla.Discussions.View');
    $CategoryData = $this->SQL->Get();
    $AllowedForums = array();
    foreach ($CategoryData->Result() as $Category) {
       $AllowedForums[] = $Category->CategoryID;
    }

    // Handle Junction Filters (Chosen categories on advanced search)
    if (count($this->_JunctionFilters) > 0) {
      $SearchForums = array();
      $CountFilters = count($this->_JunctionFilters);
      for ($i = 0; $i < $CountFilters; $i++) {
        if (in_array($this->_JunctionFilters[$i], $AllowedForums) === TRUE)
          $SearchForums[] = $this->_JunctionFilters[$i];
      }
    } else {
      $SearchForums = $AllowedForums;
    }

    if (count($SearchForums) == 0)
      $SearchForums = array(0);
    
    $this->_SphinxClient->SetFilter("permissionjunctionid", $SearchForums);
    
    $this->SQL->Reset();
    
    $result = $this->_SphinxClient->Query($Search, $this->_Indexes);
    /*
    $retry_attemps = 3;
    while (!$result && $this->_SphinxClient->IsConnectError() == TRUE && $retry_attemps--) {
      usleep(250);
      $result = $this->_SphinxClient->Query($Search, $this->_Indexes);
    }
    */
    
    $discussion_ids = array(0);
    if (isset($result['matches']))
      $discussion_ids = array_keys($result['matches']);

		$this->SQL
			->Select('sd.DocumentID, sd.PrimaryID, sd.Title, sd.Summary, sd.Url, sd.DateInserted')
			->Select('u.UserID, u.Name')
			->From('SearchDocument sd')
			->Join('User u', 'sd.InsertUserID = u.UserID')
      ->Where('sd.TableName', 'Comment')
      ->WhereIn('sd.DocumentID', $discussion_ids, FALSE)
			->OrderBy('FIELD(sd.DocumentId,'.implode(',',$discussion_ids).')');
    
    $Result = $this->SQL->Get();
    $Result->TotalFound = $result['total_found'] > $MaxResults ? $MaxResults : $result['total_found'];
		$Result->DefaultDatasetType = DATASET_TYPE_ARRAY;
		
		return $Result;
  }
  
  public function Delete($Document) {
		$DocumentID = NULL;
		
		if(is_array($Document)) {
			if(!array_key_exists('DocumentID', $Document)) {
				$Data = $this->SQL->GetWhere('SearchDocument', array('TableName' => $Document['TableName'], 'PrimaryID' => $Document['PrimaryID']))->FirstRow();
				if($Data) {
					$DocumentID = $Data->DocumentID;
				} else {
					$DocumentID = NULL;
				}
				
			} else {
				$DocumentID = $Document['DocumentID'];
			}
		} else {
			$DocumentID = $Document;
		}
		
		$this->SQL->Delete('SearchDocument', array('DocumentID' => $DocumentID));
		
		if (is_numeric($DocumentID)) {
		  $this->_SphinxClient->UpdateAttributes($this->_Indexes, array("deleted"), array($DocumentID => array(1)));
	  }
  }
  
  public function Index($Document, $Keywords = NULL) {
    $DocumentID = NULL;
    
		if(is_null($Keywords)) {
			$Keywords = ArrayValue('Summary', $Document, '');
		}

		$this->FilterKeywords($Keywords);
		if(!is_array($Keywords) || count($Keywords) == 0)
			return;
		$Keywords = array_fill_keys($Keywords, NULL);
		$KeywordsToDelete = array();
		
		parent::_TrimString('Title', $Document, 50);
		parent::_TrimString('Summary', $Document, 200);
		
		$Update = false;

		if(!array_key_exists('DocumentID', $Document)) {
			$Data = $this->SQL->GetWhere('SearchDocument', array('TableName' => $Document['TableName'], 'PrimaryID' => $Document['PrimaryID']))->FirstRow();
			if($Data) {
				// The document was found, but must be updated.
				$DocumentID = $Data->DocumentID;
				$Update = true;
			} else {
				$DocumentID = NULL;
			}
		} else {
			$DocumentID = $Document['DocumentID'];
			$Update = true;
		}

		$Set = array_intersect_key($Document, array('TableName' => '', 'PrimaryID' => '', 'PermissionJunctionID' => '', 'Title' => '', 'Summary' => '', 'Url' => '', 'InsertUserID' => '', 'DateInserted' => ''));
		if(is_null($DocumentID)) {
			// There was no document so insert it.
			if(!array_key_exists('DateInserted', $Set)) {
				$Set['DateInserted'] = Format::ToDateTime();
			}
			$DocumentID = $this->SQL->Insert('SearchDocument', $Set);
		} else {
			$this->SQL->Update('SearchDocument', $Set, array('DocumentID' => $DocumentID))->Put();
    }

    if ($Update) {
      $this->_SphinxClient->UpdateAttributes($this->_Indexes, array('primaryid', 'permissionjunctionid'), array((int)$DocumentID => array((int)$Set['PrimaryID'], (int)$Set['PermissionJunctionID'])));
    }
    
    // Write a file to the cache folder that will let our cron job know when we should re-index
    touch(PATH_CACHE.DS.'sphinx.reindex');
  }
  
}
