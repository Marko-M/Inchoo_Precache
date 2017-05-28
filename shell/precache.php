<?php
/**
* Inchoo
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@magentocommerce.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Please do not edit or add to this file if you wish to upgrade
* Magento or this extension to newer versions in the future.
** Inchoo *give their best to conform to
* "non-obtrusive, best Magento practices" style of coding.
* However,* Inchoo *guarantee functional accuracy of
* specific extension behavior. Additionally we take no responsibility
* for any possible issue(s) resulting from extension usage.
* We reserve the full right not to provide any kind of support for our free extensions.
* Thank you for your understanding.
*
* @category Inchoo
* @package Precache
* @author Marko Martinović <marko.martinovic@inchoo.net>
* @copyright Copyright (c) Inchoo (http://inchoo.net/)
* @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
*/

require_once 'abstract.php';

cclass Inchoo_Shell_Precache extends Mage_Shell_Abstract
{
    protected $_precacheStores = array();

    protected $_precachePCount = 0;
    protected $_precacheUCount = 0;

    protected $_precacheBaseUrl;


    public function run() {

		set_time_limit(0);
        
		if($this->getArg('stores')) {
            $this->_precacheStores = array_merge(
                $this->_precacheStores,
                array_map(
                    'trim',
                    explode(',', $this->getArg('stores'))
                )
            );
        }

        try {

            if(!empty($this->_precacheStores)) {
                $selectedStores = '"'.implode('", "', $this->_precacheStores).'"';
            } else {
                $selectedStores = 'All';
            }

            printf(
                'Selected stores: %s'."\n",
                $selectedStores
            );

            echo "\n";

            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                $this->_precacheProcessStore($store);
            }

            printf(
                'Done processing.'."\n"
                    .'Total processed stores count: %d'."\n"
                    .'Total processed pages count: %d'."\n",
                $this->_precacheSCount, $this->_precacheUCount
            );

        } catch (Exception $e) {
            echo $e->getMessage().'@'.time();
        }

    }

    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f precache.php  --[options]

  --stores <names>       Process only these stores (comma-separated store view names)

  help                   This help

USAGE;
    }

    protected function _precacheProcessStore($store)
    {
        $storeName = $store->getName();

        if(!empty($this->_precacheStores) &&
                !in_array($storeName, $this->_precacheStores)) {
            return;
        }

        printf('Processing "%s" store'."\n", $storeName);

        $this->_precacheSCount++;

        Mage::app()->setCurrentStore($store->getId());

		$collection = Mage::getModel('sitemap/sitemap')->getCollection();

		var_dump($collection);

		foreach($collection as $sitemap) {
			echo $url;
			$url = substr_replace(Mage::getBaseUrl() ,"",-1) . $sitemap->getData('sitemap_path') . $sitemap->getData('sitemap_filename');
			printf("\t%s%s: %d",$storeName, $url, $this->_precacheHttpRequest($categoryUrl));
			$this->_precacheUCount++;
		}
        echo "\n";
    }

    protected function _precacheHttpRequest($url)
    {
        $client = new Zend_Http_Client($url, array('timeout' => 60));

        $response = $client->request();

        return (int) $response->getStatus();
    }
    
}

$shell = new Inchoo_Shell_Precache();
$shell->run();

