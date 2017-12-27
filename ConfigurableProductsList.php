<?php

namespace Test\Custom\Model;
use Magento\Framework\Api\SortOrder;
use Ewall\Mobileshop\Api\ConfigurableProductsInterface;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Catalog\Helper\ImageFactory as ProductImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
class ConfigurableProductsList implements ConfigurableProductsInterface
{
    /**
     * 
     *
     * @var \Magento\Catalog\Model\Layer\Category\FilterableAttributeList
     */
    protected $_filterableAttributeList;

    /**
     * 
     *
     * @var \Magento\Catalog\Model\Layer\Resolver
     */
    protected $_layerResolver;

    /**
     * 
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectmanager;

    /**
     * 
     *
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $collectionfactory;

    /**
     * 
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storemanagerinterface;

    /**
     * 
     *
     * @var \Magento\CatalogInventory\Model\Stock\StockItemRepository
     */
    protected $stockinformation;

    /**
     * @var \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

        /**
     *@var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;
    
    /**
     *@var \Magento\Catalog\Helper\ImageFactory
     */
    protected $productImageHelper;

    /**
     * @var \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface
     */
    protected $extensionAttributesJoinProcessor;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $metadataService;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
     /**
     * @param  \Magento\Catalog\Model\Layer\Category\FilterableAttributeList
     * @param \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param  \Magento\Catalog\Model\Layer\Resolver
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface
     * @param  \Magento\CatalogInventory\Model\Stock\StockItemRepository
     * @param  \Magento\Framework\ObjectManagerInterface
     * @param \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributesJoinProcessor
     **/

    public function __construct(
        \Magento\Catalog\Model\Layer\Category\FilterableAttributeList $filterableAttributeList,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionfactory,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockinformation,
        \Magento\Store\Model\StoreManagerInterface $storemanagerinterface,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface,
        AppEmulation $appEmulation,
        \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributesJoinProcessor,
        ProductImageHelper $productImageHelper
        ){
        
        $this->_filterableAttributeList   = $filterableAttributeList;
        $this->_layerResolver   = $layerResolver;
        $this->_objectmanager = $objectManager;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionfactory = $collectionfactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->metadataService = $metadataServiceInterface;
        $this->stockinformation = $stockinformation;
        $this->storemanagerinterface = $storemanagerinterface;
        $this->appEmulation = $appEmulation;
        $this->productImageHelper = $productImageHelper;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
    }


    
    /**
     * {@inheritdoc}
     */
    public function getConfigurableProductsList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->collectionfactory->create();
        $this->extensionAttributesJoinProcessor->process($collection);
        $collection->addAttributeToSort('created_at', 'desc');
        $collection->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('type_id',\Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE);
        $collection->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        foreach ($this->metadataService->getList($this->searchCriteriaBuilder->create())->getItems() as $metadata) {
            $collection->addAttributeToSelect($metadata->getAttributeCode());
        }

        //Add filters from root filter group to the collection
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
        /** @var SortOrder $sortOrder */
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
            $field = $sortOrder->getField();
            $collection->addOrder(
                $field,
                ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        return $searchResult;
    }


    public function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        Collection $collection
    ) {
        $fields = [];
        $categoryFilter = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $conditionType = $filter->getConditionType() ? $filter->getConditionType() : 'eq';

            if ($filter->getField() == 'category_id') {
                $categoryFilter[$conditionType][] = $filter->getValue();
                continue;
            }
            $fields[] = ['attribute' => $filter->getField(), $conditionType => $filter->getValue()];
        }

        if ($categoryFilter) {
            $collection->addCategoriesFilter($categoryFilter);
        }

        if ($fields) {
            $collection->addFieldToFilter($fields);
        }
    }




    /**
     * Helper function that provides full cache image url
     * @param \Magento\Catalog\Model\Product
     * @return string
     */
    public function getImageUrl($product, string $imageType = ''){
        $storeId = $this->storemanagerinterface->getStore()->getId();
        $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $imageUrl = $this->productImageHelper->create()->init($product, $imageType)->getUrl();
        $this->appEmulation->stopEnvironmentEmulation();
    
        return $imageUrl;
    }

}
