<?php
// wcf imports
require_once(WCF_DIR.'lib/acp/package/plugin/AbstractXMLPackageInstallationPlugin.class.php');

/**
 * This PIP deletes files on update.
 * 
 * @author 	Stefan Hahn
 * @copyright	2011 Stefan Hahn
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.leon.wcf.package.update.delete.files
 * @subpackage	acp.package.plugin
 * @category 	Community Framework
 */
class FilesDeletePackageInstallationPlugin extends AbstractXMLPackageInstallationPlugin {
	public $tagName = 'filesdelete';
	public $tableName = 'package_installation_file_log';
	
	/** 
	 * @see PackageInstallationPlugin::install()
	 */
	public function install() {
		parent::install();
		
		$xml = $this->getXML();
		$availableFiles = $this->getAvailableFiles();
		
		if (!$xml) {
			return;
		}
		
		$xml = $xml->getElementTree('data');
		
		foreach ($xml['children'] as $key => $block) {
			if (count($block['children'])) {
				if ($block['name'] === 'delete') {
					if ($this->installation->getAction() === 'update') {
						$files = array();
						
						foreach ($block['children'] as $fileInfo) {
							if (!isset($fileInfo['attrs']['name'])) {
								throw new SystemException("Required 'name' attribute for 'file'-tag is missing.");
							}
							
							if (!in_array($fileInfo['attrs']['name'], $availableFiles)) {
								throw new SystemException('A package can delete its own files only');
							}
							
							$files[] = $fileInfo['attrs']['name'];
						}
						
						if (count($files)) {
							$fileNamesStr = "'".implode("','", array_map('escapeString', $files))."'";
							$sql = "DELETE FROM	wcf".WCF_N."_".$this->tableName."
								WHERE		filename IN (".$fileNamesStr.")
										AND packageID = ".$this->installation->getPackageID();
							WCF::getDB()->sendQuery($sql);
							
							$this->deleteFiles($files);
						}
						
						
					}
					else {
						throw new SystemException('Files can be deleted during update only');
					}
				}
			}
		}
	}
	
	/**
	 * @see	 PackageInstallationPlugin::hasUninstall()
	 */
	public function hasUninstall() {
		EventHandler::fireAction($this, 'hasUninstall');
		
		return false;
	}
	
	/**
	 * Gets all files which are accessible by the current package
	 * 
	 * @return	array<string>
	 */
	protected function getAvailableFiles() {
		$sql = "SELECT	filename
			FROM	wcf".WCF_N.'_'.$this->tableName."
			WHERE	packageID = ".$this->installation->getPackageID();
		$result = WCF::getDB()->sendQuery($sql);
		
		$availableFiles = array();
		while ($row = WCF::getDB()->fetchArray($result)) {
			$availableFiles[] = $row['filename'];
		}
		
		return $availableFiles;
	}
	
	/**
	 * Deletes the given list of files
	 *
	 * @param 	array<string> 	$files
	 * @param	boolean		$deleteEmptyDirectories
	 * @param	booelan		$deleteEmptyTargetDir
	 */
	protected function deleteFiles($files, $deleteEmptyTargetDir = false, $deleteEmptyDirectories = true) {
		$targetDir = FileUtil::addTrailingSlash(FileUtil::getRealPath(WCF_DIR.$this->installation->getPackage()->getDir()));
		
		if ($ftp = $this->installation->checkSafeMode()) { 
			require_once(WCF_DIR.'lib/system/setup/FTPUninstaller.class.php');
			new FTPUninstaller($targetDir, $files, $ftp, $deleteEmptyTargetDir, $deleteEmptyDirectories);
		}
		else {
			require_once(WCF_DIR.'lib/system/setup/FileUninstaller.class.php');
			new FileUninstaller($targetDir, $files, $deleteEmptyTargetDir, $deleteEmptyDirectories);
		}
	}
}
