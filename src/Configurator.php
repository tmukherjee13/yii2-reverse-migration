<?php 

namespace tmukherjee13\migration;

/**
* 			
*/
class Configurator
{

	const TYPE_DATA = '@tmukherjee13/migration/src/views/templateData.php';
	const TYPE_CLASS = '@tmukherjee13/migration/src/views/template.php';
	const TYPE_TABLE = '@tmukherjee13/migration/src/views/templateTable.php';


	
	public static function getTemplate($type)
	{
		return $type;
	}
}