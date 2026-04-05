<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://opensource.org/license/mit MIT
*/
declare(strict_types=1);

namespace SourcePot\checkentries;

class checkentries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    public const ONEDIMSEPARATOR='|[]|';

    private const CONTENT_STRUCTURE_PARAMS=[
        'Target success'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Target failure'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Rules match<br/>sample probability'=>['method'=>'select','excontainer'=>TRUE,'value'=>100,'options'=>[100=>'100%',90=>'90%',80=>'80%',70=>'70%',60=>'60%',50=>'50%',40=>'40%',30=>'30%',20=>'20%',10=>'10%',5=>'5%',2=>'2%',1=>'1%'],'keep-element-content'=>TRUE],
        'Rules no match<br/>sample probability'=>['method'=>'select','excontainer'=>TRUE,'value'=>5,'options'=>[100=>'100%',90=>'90%',80=>'80%',70=>'70%',60=>'60%',50=>'50%',40=>'40%',30=>'30%',20=>'20%',10=>'10%',5=>'5%',2=>'2%',1=>'1%'],'keep-element-content'=>TRUE],
        'Column 1'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','addParentKeys'=>TRUE],
        'Column 2'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'SKIP','addParentKeys'=>TRUE,'addColumns'=>['SKIP'=>'-']],
        'Column 3'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'SKIP','addParentKeys'=>TRUE,'addColumns'=>['SKIP'=>'-']],
    ];
        
    private const CONTENT_STRUCTURE_RULES=[
        '...'=>['method'=>'select','excontainer'=>TRUE,'value'=>'||','options'=>['&&'=>'AND','||'=>'OR'],'keep-element-content'=>TRUE],
        'Property'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Folder','addSourceValueColumn'=>FALSE],
        'Property data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Condition'=>['method'=>'select','excontainer'=>TRUE,'value'=>'strpos','options'=>\SourcePot\Datapool\Foundation\Computations::CONDITION_TYPES,'keep-element-content'=>TRUE],
        'Compare value'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'P532132WEDE','excontainer'=>TRUE],
    ];
        
    private $entryTable='';
    private $entryTemplate=[];

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }
    
    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }
    
    public function getEntryTable():string{
        return $this->entryTable;
    }

    public function dataProcessor(array $callingElementSelector=[],string $action='info')
    {
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->run($callingElement,$testRunOnly=FALSE),
                'test'=>$this->run($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getWidget($callingElement),
                'settings'=>$this->getSettings($callingElement),
                'info'=>$this->getInfo($callingElement),
            };
        }
    }

    private function getWidget($callingElement){
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entries','generic',$callingElement,['method'=>'getEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
        // get column options
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        $columns=[];
        foreach(current($base['processingparams']??[])['Content']??[] as $key=>$value){
            if (strpos($key,'Column ')===FALSE || strpos($value,'SKIP')!==FALSE){continue;}
            $columns[]=['Column'=>$value];
        }
        // manual check
        $settings=['orderBy'=>'Name','isAsc'=>TRUE,'limit'=>1,'hideUpload'=>TRUE,'hideApprove'=>FALSE,'hideDecline'=>FALSE,'hideDelete'=>TRUE,'hideRemove'=>TRUE,'columns'=>$columns];
        $selector=$callingElement['Content']['Selector'];
        $selector['Params!']='%"'.$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId().'_action"%';
        $wrapperSetting=[];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry check '.time(),'entryList',$selector,$settings,$wrapperSetting);
        return $html;
    }

    private function getInfo($callingElement){
        $matrix=[];
        $matrix['']['value']='';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        return $html;
    }
       
    public function getEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->run($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->run($arr['selector'],TRUE);
        }
        // build html
        $btnArr=['tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $matrix=[];
        $btnArr['value']='Check';
        $btnArr['key']=['test'];
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Process entries';
        $btnArr['key']=['run'];
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Entries']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            //if ($caption==='Entries'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }
    
    private function getSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entries entries settings','generic',$callingElement,['method'=>'getEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->processingParams($arr['selector']);
        $arr['html'].=$this->processingRules($arr['selector']);
        return $arr;
    }

    private function processingParams($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Entries control: Select mapping target and type';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function processingRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Entries filter rules: defines entry filter for manual checking';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function run($callingElement,$testRun=FALSE){
        $base=['processingparams'=>[],'processingrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // add to base canvas elements->['EntryId'=>'Name']
        $canvasElements=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($callingElement['Folder']);
        foreach($canvasElements as $index=>$canvasElement){
            $base[$canvasElement['EntryId']]=$canvasElement['Content']['Style']['Text'];
        }
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Entries'=>[]];
        // loop through entries
        $params=current($base['processingparams']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read') as $sourceEntry){
            $result['Entries'][$sourceEntry['Name']]=[
                'File attached'=>FALSE,
                'Property missing'=>FALSE,
                'Ready for<br/>manual check'=>FALSE,
                'Rule match'=>FALSE,
                'User action'=>'none',
                'Random forward'=>''
            ];
            $result['Entries'][$sourceEntry['Name']]['File attached']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($sourceEntry['Params']['File']));
            $result=$this->processEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function processEntry($base,$sourceEntry,$result,$testRun){
        $params=current($base['processingparams']);
        // check for added manual action
        $userKey=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId().'_action';
        $targetEntryId=FALSE;
        if (isset($sourceEntry['Params']['User'][$userKey]['action'])){
            if ($sourceEntry['Params']['User'][$userKey]['action']==='approve'){
                // entry approved
                $targetEntryId=$params['Content']['Target success'];
            } else if ($sourceEntry['Params']['User'][$userKey]['action']==='decline'){
                // declined entry
                $targetEntryId=$params['Content']['Target failure'];
            } else {
                $this->oc['logger']->log('notice','User action "{action}" did not match "approve" or "decline"',['action'=>$sourceEntry['Params']['User'][$userKey]['action']]);
            }
            if ($targetEntryId){
                $result['Entries'][$sourceEntry['Name']]['User action']='<b>'.$sourceEntry['Params']['User'][$userKey]['action'].'</b>';
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$targetEntryId],TRUE,$testRun);
            }
        }
        // check if rules were already applied
        $rulesWereAppliedAlready=$this->oc['SourcePot\Datapool\Tools\MiscTools']->wasTouchedByClass($sourceEntry,__CLASS__,$testRun);
        $result['Entries'][$sourceEntry['Name']]['Ready for<br/>manual check']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rulesWereAppliedAlready);
        // check rule match
        $ruleMatch=NULL;
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($base['processingrules'] as $ruleIndex=>$rule){
            $rule['Content']['ruleIndex']=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleIndex);
            if (!isset($flatSourceEntry[$rule['Content']['Property']])){
                $result['Entries'][$sourceEntry['Name']]['Property missing']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(TRUE);
                continue;
            } else {
                $result['Entries'][$sourceEntry['Name']]['Property missing']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE);
            }
            $property=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($flatSourceEntry[$rule['Content']['Property']],$rule['Content']['Property data type']);
            $conditionMet=$this->oc['SourcePot\Datapool\Foundation\Computations']->isTrue($property,$rule['Content']['Compare value'],$rule['Content']['Condition']);
            if ($ruleMatch===NULL){
                $ruleMatch=$conditionMet;
            } else if ($rule['Content']['...']==='&&'){
                $ruleMatch=$ruleMatch && $conditionMet;
            } else if ($rule['Content']['...']==='||'){
                $ruleMatch=$ruleMatch || $conditionMet;
            } else {
                $this->oc['logger']->log('notice','Rule "{ruleIndex}" is invalid, key "... = {...}" is undefined',$rule['Content']);
            }
        }
        $result['Entries'][$sourceEntry['Name']]['Rule match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleMatch);
        if ($ruleMatch){
            if (mt_rand(0,99)<$params['Content']['Rules match<br/>sample probability'] || $rulesWereAppliedAlready){
                // manual check
            } else {
                // forward to success target
                $targetEntryId=$params['Content']['Target success'];
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$targetEntryId],TRUE,$testRun);
                $result['Entries'][$sourceEntry['Name']]['Random forward']=$base[$targetEntryId];
            }
        } else {
            if (mt_rand(0,99)<$params['Content']['Rules no match<br/>sample probability'] || $rulesWereAppliedAlready){
                // manual check
            } else {
                // forward to success target
                $targetEntryId=$params['Content']['Target success'];
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$targetEntryId],TRUE,$testRun);
                $result['Entries'][$sourceEntry['Name']]['Random forward']=$base[$targetEntryId];
            }
        }
        return $result;
    }
}
?>