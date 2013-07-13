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

class Inchoo_Shell_Precache extends Mage_Shell_Abstract
{
    protected $_precacheStores = array();

    protected $_precacheCategories = array();

    public function __construct() {
        parent::__construct();

        set_time_limit(0);
    }

    public function run() {
        if($this->getArg('stores')) {
            $this->_precacheStores = array_merge(
                $this->_precacheStores,
                array_map(
                    'trim',
                    explode(',', $this->getArg('stores'))
                )
            );
        }

        if($this->getArg('categories')) {
            $this->_precacheCategories = array_merge(
                $this->_precacheCategories,
                array_map(
                    'trim',
                    explode(',', $this->getArg('categories'))
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

            if(!empty($this->_precacheCategories)) {
                $selectedCategories = '"'.implode('", "', $this->_precacheCategories).'"';
            } else {
                $selectedCategories = 'All';
            }

            printf(
                'Selected categories: %s'."\n",
                '"'.implode('", "', $this->_precacheCategories).'"'
            );

            echo "\n";

            $pcount = 0;
            $ccount = 0;
            $scount = 0;
            $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $productSuffix = Mage::helper('catalog/product')->getProductUrlSuffix();

            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                $storeName = $store->getName();

                if(!empty($this->_precacheStores) &&
                        !in_array($storeName, $this->_precacheStores)) {
                    continue;
                }

                $scount++;

                Mage::app()->setCurrentStore($store->getId());

                printf('Processing "%s" store'."\n", $storeName);

                $storeCode = $store->getCode();

                $categoryCollection = $this->_factory->getModel('catalog/category')
                    ->getCollection()
                    ->addNameToResult()
                    ->addUrlRewriteToResult()
                    ->addIsActiveFilter()
                    ->addAttributeToFilter('parent_id', $store->getRootCategoryId());

                if(!empty($this->_precacheCategories)) {
                    $categoryCollection
                        ->addAttributeToFilter(array(
                            array(
                                'attribute' => 'name',
                                'in' => $this->_precacheCategories,
                            )
                        ));
                }

                if(!($csize = $categoryCollection->getSize())) {
                    echo 'No active categories match in this store. Continue...'."\n";

                    continue;
                }

                printf('Total category count in this store is %d'."\n", $csize);

                foreach ($categoryCollection as $category) {
                    $ccount++;

                    $categoryName = $category->getName();

                    printf('Processing "%s" category'."\n", $categoryName);

                    $productCollection = $category->getProductCollection()
                        ->addAttributeToSelect('name')
                        ->addAttributeToSelect('url_key');

                    $this->_factory->getSingleton('catalog/product_visibility')
                        ->addVisibleInCatalogFilterToCollection($productCollection);
                    $this->_factory->getSingleton('catalog/product_status')
                        ->addVisibleFilterToCollection($productCollection);

                    if(!($psize = $productCollection->getSize())) {
                        printf('"%s" category has no enabled and visible in catalog products. Continue...'."\n", $categoryName);

                        continue;
                    }

                    printf('Total product count in this category is %d'."\n", $psize);

                    foreach ($productCollection as $product) {
                        printf('%d. %s:'."\n", ++$pcount, $product->getSku());

                        $canonicalUrl = $product->getProductUrl();

                        printf(
                            "\t".'Canonical URL: %s [%d]'."\n",
                            $canonicalUrl,
                            $this->_precacheHttpRequest($canonicalUrl)
                        );

                        /*
                         * $category->getRequestPath() and $product->getUrlKey()
                         * sometimes returns null due to bug with duplicate
                         * request_path/url_key in multiple stores.
                         *
                         * Related Magento bug:
                         * http://www.magentocommerce.com/bug-tracking/issue?issue=15035
                         *
                         */
                        if($category->getRequestPath()) {
                            $categoryUrlKey = $category->getRequestPath();
                            /* Fallback - use previous store's category url key if
                             * that key exists or else ignore rewrite information
                             */
                        }

                        if($categoryUrlKey && ($productUrlKey = $product->getUrlKey())) {
                            // $categoryUrlKey and $productUrlKey is not null
                            $categoryUrl = $baseUrl
                                .$storeCode
                                .'/'
                                .$categoryUrlKey
                                .'/'
                                .$productUrlKey
                                .'.'
                                .$productSuffix;
                        } else{
                            // Fallback - don't use rewrite
                            $categoryUrl = Mage::getUrl(
                                'catalog/product/view',
                                array('id' => $product->getId())
                            );
                        }

                        printf(
                            "\t".'Category URL: %s [%d]'."\n",
                            $categoryUrl,
                            $this->_precacheHttpRequest($categoryUrl)
                        );
                    }
                }

                echo "\n";
            }

            printf(
                'Done processing.'."\n"
                    .'Total processed stores count: %d'."\n"
                    .'Total processed categories count: %d'."\n"
                    .'Total processed products count: %d'."\n",
                $scount, $ccount, $pcount
            );

        } catch (Exception $e) {
            echo $e->getMessage().'@'.time();
        }

    }

    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f precache.php -- [options]

  --stores <names>       Process only these stores (comma-separated)
  --categories <names>   Process only these categories (comma-separated)

  help                   This help

USAGE;
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