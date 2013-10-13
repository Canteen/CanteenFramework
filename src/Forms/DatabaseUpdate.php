<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	
	/**
	*  Update the database to the latest version.  Located in the namespace __Canteen\Forms__.
	*  @class DatabaseUpdate
	*  @extends FormBase
	*/
	class DatabaseUpdate extends FormBase
	{	
		/**
		*  Process the form and handle the $_POST data.
		*  @method process 
		*/
		public function process()
		{
			$updatesFolder = $this->verify(ifsetor($_POST['updatesFolder']), Validate::URI, true);
			$version = $this->verify(ifsetor($_POST['version']), null, true);
			$variableName = $this->verify(ifsetor($_POST['variableName']), Validate::URI, true);
			$targetVersion = $this->verify(ifsetor($_POST['targetVersion']), null, true);
			
			if (!$updatesFolder)
			{
				$this->error("There was a problem with the location of updates: " . ifsetor($_POST['updatesFolder']));
			}
			if (!$version)
			{
				$this->error("There was a problem with the current version of database");
			}
			if (!$variableName)
			{
				$this->error("There was a problem with the name of the version config property name");
			}
			if (!$targetVersion)
			{
				$this->error("There was a problem with the target version of the database");
			}
			
			if (!$this->ifError())
			{
				while ($version < $targetVersion && file_exists($updatesFolder.$version.'.php'))
				{
					// Output buffer the update include
					// should output a new version number
					ob_start();
			            include $updatesFolder.$version.'.php';
			            $version = ob_get_contents();
			        ob_end_clean();

					// Update the config and the site version number
					$this->service('config')->updateValue($variableName, $version);
					$this->site()->setData($variableName, $version);
				}
			}
		}
	}
}	