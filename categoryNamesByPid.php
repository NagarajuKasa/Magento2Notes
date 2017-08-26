


<?php
use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

ini_set('display_errors', 1);

$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
$product = $objectManager->create('Magento\Catalog\Model\Product')->load(10);

if ($categoryIds = $product->getCustomAttribute('category_ids')) {
    foreach ($categoryIds->getValue() as $categoryId) {
		
		$category=$objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
		
		echo $category->getName()."<br>";	
       
    }
}

?>


