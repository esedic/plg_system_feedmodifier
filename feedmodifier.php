<?php defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSystemFeedModifier extends JPlugin
{
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->app    = JFactory::getApplication();
		$this->format = $this->app->input->get('format');
	}

	function onAfterRender()
	{
		if (!$this->app->isAdmin() && $this->format == 'feed')
		{
			$buffer = $this->app->getBody();
			
			$responseXml = new SimpleXMLElement($buffer);	

			foreach($responseXml->channel->item as $item ) :
							
				preg_match_all('/\d+/', $item->link, $matches);  // get id from url
			
				$articleId = $matches[0][1] ? $matches[0][1] : $matches[0][0]; // change to 0;
								
				$time =  $this->getArticleFieldValue($articleId, 33);
				$start_date = $this->getArticleFieldValue($articleId, 16) ? $this->getArticleFieldValue($articleId, 16) : $this->getArticleFieldValue($articleId, 13);
				$start_date = date(DATE_ISO8601, strtotime($start_date.' '.$time));
				$item->{'ical:dtstart'} = $start_date;
				
				if ( $end_date = $this->getArticleFieldValue($articleId, 14) ) {
					$end_date = date(DATE_ISO8601, strtotime($end_date));
					$item->{'ical:dtend'} = $end_date;
				}
	
				$item->{'ical:location'} =  $this->getArticleFieldValue($articleId, 12, 1);
				
			endforeach;
		
			$newString = $responseXml->asXML();
			
			$this->app->setBody($newString);
		}
	}
	
	
	private function getArticleFieldValue ($articleId, $fieldId, $ExtraFieldValue = 0) {
		
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		
		$query->select(
			'i.extra_fields as extra_fields' 
		)
		->from('#__k2_items as i')
		->where('i.id ='.$articleId);
			
		$db->setQuery($query);
		
		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JError::raiseError(500, $e->getMessage());
		}

		$fieldValue = $db->loadResult();
		$fieldValue = $fieldValue ? json_decode($fieldValue) : '';
	
		foreach((array)$fieldValue as $value) {
			if($value->id == $fieldId) {
				if($ExtraFieldValue == 1) {
					return	$this->getExtraFieldValue($value->value, $fieldId);
				} else {
					return 	$value->value;
				}
			}
		}
		return false;	
	}
		
	private function getExtraFieldValue ($fieldValue, $fieldId) {
		
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		
		$query->select(
			'ef.value as value' 
		)
		->from('#__k2_extra_fields as ef')
		->where('ef.id ='.$fieldId);
			
		$db->setQuery($query);
		
		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JError::raiseError(500, $e->getMessage());
		}

		$result = $db->loadResult();
		
		$result = $result ? json_decode($result) : '';
		
		foreach((array)$result as $value) {
			if($value->value == $fieldValue) {
				return $value->name;
			}
		}
		return false;	
	}
}