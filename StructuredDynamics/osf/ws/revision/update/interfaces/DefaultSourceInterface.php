<?php
  
  namespace StructuredDynamics\osf\ws\revision\update\interfaces; 
  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\framework\Subject;
  use \StructuredDynamics\osf\framework\Namespaces;
  use \StructuredDynamics\osf\ws\crud\update\CrudUpdate;
  use \StructuredDynamics\osf\ws\revision\read\RevisionRead;
  use \StructuredDynamics\osf\ws\crud\delete\CrudDelete;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
    }
    
    public function processInterface()
    {  
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {  
        // Validate the lifecycle
        if($this->ws->lifecycle != 'published' &&
           $this->ws->lifecycle  != 'archive' &&
           $this->ws->lifecycle  != 'experimental' &&
           $this->ws->lifecycle  != 'pre_release' &&
           $this->ws->lifecycle  != 'staging' &&
           $this->ws->lifecycle  != 'harvesting' &&
           $this->ws->lifecycle  != 'unspecified')
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, '',
            $this->ws->errorMessenger->_303->level);      
            
          return;
        }          
        
        $revisionsDataset = rtrim($this->ws->dataset, '/').'/revisions/';
        
        // If the lifecycle stage is 'published', then we have to:
        //  (1) Change the status of the currently published record from 'published' to 'archive'
        //  (2) Change the lifecycle stage of the new record to publish to 'published'
        //  (3) Update the record, in the dataset, using the revision record.
        if($this->ws->lifecycle == 'published')
        {            
          // (1) Change the status of the currently published record from 'published' to 'archive'
          $this->ws->sparql->query("modify <" . $revisionsDataset . ">
                    delete
                    { 
                      ?revision <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#published> .
                    }
                    insert
                    {
                      ?revision <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#archive> .
                    }
                    where
                    {
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionUri> ?revisionUri .
                    
                      ?revision <http://purl.org/ontology/wsf#revisionUri> ?revisionUri ;
                                <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#published> .
                    }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_304->level);

            return;
          }
          
          // (2) Change the lifecycle stage of the new record to publish to 'published' 
          $this->ws->sparql->query("modify <" . $revisionsDataset . ">
                    delete
                    { 
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?revisionStatus .
                    }
                    insert
                    {
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#published> .
                    }
                    where
                    {
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?revisionStatus .
                    }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_305->level);

            return;
          }

          // (3) Update the record, in the dataset, using the revision record.        
          $revisionRead = new RevisionRead($this->ws->revuri, $this->ws->dataset, 'record');
              
          $revisionRead->ws_conneg('application/rdf+xml', 
                                   (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                   (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                   (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ""));

          $revisionRead->process();    
          
          if($revisionRead->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($revisionRead->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($revisionRead->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($revisionRead->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($revisionRead->pipeline_getError()->id, 
              $revisionRead->pipeline_getError()->webservice,
              $revisionRead->pipeline_getError()->name, $revisionRead->pipeline_getError()->description,
              $revisionRead->pipeline_getError()->debugInfo, $revisionRead->pipeline_getError()->level);

            return;              
          }

          $crudUpdate = new CrudUpdate($revisionRead->ws_serialize(), "application/rdf+xml", $this->ws->dataset, 
                                       'default', '', 'published', 'false');

          $crudUpdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

          $crudUpdate->process();
          
          if($crudUpdate->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($crudUpdate->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($crudUpdate->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($crudUpdate->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($crudUpdate->pipeline_getError()->id, $crudUpdate->pipeline_getError()->webservice,
              $crudUpdate->pipeline_getError()->name, $crudUpdate->pipeline_getError()->description,
              $crudUpdate->pipeline_getError()->debugInfo, $crudUpdate->pipeline_getError()->level);

            return;
          }            
        }
        else
        {
          // If the status of the revision to change was 'published' then we:
          //   (1) Change the status of the record to the new status
          //   (2) We delete the record from the "public" dataset
          
          // Check the current status of the revision to update
          $this->ws->sparql->query("select ?status ?uri
                    from <" . $revisionsDataset . ">
                    where
                    {
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionUri> ?uri .
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?status .
                    }
                    limit 1
                    offset 0");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_306->level);

            return;
          }
          else
          {     
            $this->ws->sparql->fetch_binding();
            $status = $this->ws->sparql->value('status');
            $uri = $this->ws->sparql->value('uri');
                                
            // If the status of this revision was published, then we delete the record in the "public" dataset
            // this act like unpublished a record. It will exists in the revisions graph, but not in the
            // "public" dataset anymore (until it gets re-published)
            if($status == Namespaces::$wsf.'published')
            {
              // Check to delete potential datasets that have been created within OSF
              // Use the default 'soft' mode such that we keep all the revisions
              $crudDelete = new CrudDelete($uri, $this->ws->dataset);

              $crudDelete->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                     (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                     (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                     (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

              $crudDelete->process();

              if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
              {
                $this->ws->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
                $this->ws->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
                $this->ws->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
                $this->ws->conneg->setError($crudDelete->pipeline_getError()->id,
                  $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
                  $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
                  $crudDelete->pipeline_getError()->level);

                return;
              }
            }
            
            $this->ws->sparql->query("modify <" . $revisionsDataset . ">
                      delete
                      { 
                        <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?revisionStatus .
                      }
                      insert
                      {
                        <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#".$this->ws->lifecycle."> .
                      }
                      where
                      {
                        <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?revisionStatus .
                      }");

            if($this->ws->sparql->error())
            {
              $this->ws->conneg->setStatus(500);
              $this->ws->conneg->setStatusMsg("Internal Error");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, 
                $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_305->level);

              return;
            }              
          }
        }
        
        // Invalidate caches
        if($this->ws->memcached_enabled)
        {
          $this->ws->invalidateCache('revision-read');
          $this->ws->invalidateCache('revision-lister');
          $this->ws->invalidateCache('search');
          $this->ws->invalidateCache('sparql');        
          $this->ws->invalidateCache('crud-read');         
        }
      }      
    }
  }
?>
