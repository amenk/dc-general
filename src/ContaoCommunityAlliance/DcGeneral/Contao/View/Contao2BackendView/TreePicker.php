<?php
/**
 * PHP version 5
 *
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView;

use BackendTemplate;
use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Backend\AddToUrlEvent;
use ContaoCommunityAlliance\Contao\Bindings\Events\Controller\ReloadEvent;
use ContaoCommunityAlliance\Contao\Bindings\Events\Image\GenerateHtmlEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\DataDefinition\Definition\Contao2BackendViewDefinitionInterface;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\ModelToLabelEvent;
use ContaoCommunityAlliance\DcGeneral\Controller\TreeNodeStates;
use ContaoCommunityAlliance\DcGeneral\Data\CollectionInterface;
use ContaoCommunityAlliance\DcGeneral\Data\DCGE;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\View\DefaultModelFormatterConfig;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\View\ListingConfigInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\View\ModelFormatterConfigInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\ModelRelationship\FilterBuilder;
use ContaoCommunityAlliance\DcGeneral\DC_General;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\DcGeneral\Factory\DcGeneralFactory;
use FrontendTemplate;

/**
 * Provide methods to handle input field "tableTree".
 *
 * @copyright  2014 CyberSpectrum
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class TreePicker extends \Widget
{
    /**
     * Submit user input.
     *
     * @var boolean
     */
    protected $blnSubmitInput = true;

    /**
     * The template.
     *
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * The sub template to use when generating.
     *
     * @var string
     */
    protected $subTemplate = 'widget_treepicker';

    /**
     * The ajax id.
     *
     * @var string
     */
    protected $strAjaxId;

    /**
     * The ajax key.
     *
     * @var string
     */
    protected $strAjaxKey;

    /**
     * The ajax name.
     *
     * @var string
     */
    protected $strAjaxName;

    /**
     * The data Container.
     *
     * @var DC_General
     */
    protected $dataContainer;

    /**
     * The source data container name.
     *
     * @var string
     */
    protected $sourceName;

    /**
     * The field type to use.
     *
     * This may be either "radio" or "checkbox"
     *
     * @var string
     */
    protected $fieldType = 'radio';

    /**
     * The icon to use in the title section.
     *
     * @var string
     */
    protected $titleIcon = 'system/themes/default/images/page.gif';

    /**
     * The title to display.
     *
     * @var string
     */
    protected $title;

    /**
     * The data container for the item source.
     *
     * @var DC_General
     */
    protected $itemContainer;

    /**
     * The property used for ordering.
     *
     * @var string
     */
    protected $orderField;

    /**
     * The tree nodes to be handled.
     *
     * @var TreeNodeStates
     */
    protected $nodeStates;

    /**
     * Create a new instance.
     *
     * @param array      $attributes    The custom attributes.
     *
     * @param DC_General $dataContainer The data container.
     *
     * @internal param $array
     */
    public function __construct($attributes = array(), DC_General $dataContainer = null)
    {
        parent::__construct($attributes);

        $this->setUp($dataContainer);
    }

    /**
     * Setup all local values and create the dc instance for the referenced data source.
     *
     * @param DC_General $dataContainer The data container to use.
     *
     * @return void
     */
    protected function setUp(DC_General $dataContainer = null)
    {
        $this->dataContainer = $dataContainer ?: $this->objDca;

        if (!$this->dataContainer) {
            return;
        }

        $environment = $this->dataContainer->getEnvironment();

        if (!$this->sourceName) {
            $property = $environment
                ->getDataDefinition()
                ->getPropertiesDefinition()
                ->getProperty($environment->getInputProvider()->getValue('name'));

            foreach ($property->getExtra() as $k => $v) {
                $this->$k = $v;
            }

            $name           = $environment->getInputProvider()->getValue('name');
            $this->strField = $name;
            $this->strName  = $name;
            $this->strId    = $name;
            $this->label    = $property->getLabel() ?: $name;
            $this->strTable = $environment->getDataDefinition()->getName();
        }

        $factory             = new DcGeneralFactory();
        $this->itemContainer = $factory
            ->setContainerName($this->sourceName)
            ->setTranslator($environment->getTranslator())
            ->setEventDispatcher($environment->getEventDispatcher())
            ->createDcGeneral();
    }

    /**
     * Update the value via ajax and redraw the widget.
     *
     * @param string     $ajaxAction    Not used in here.
     *
     * @param DC_General $dataContainer The data container to use.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function updateAjax($ajaxAction, $dataContainer)
    {
        if ($ajaxAction !== 'reloadGeneralTreePicker') {
            return '';
        }

        $this->setUp($dataContainer);
        $environment = $this->dataContainer->getEnvironment();
        $this->value = $environment->getInputProvider()->getValue('value');

        $result = '<input type="hidden" value="' . $this->strName . '" name="FORM_INPUTS[]">' .
            '<h3><label>' . $this->label . '</label></h3>' . $this->generate();

        $label = $environment
            ->getDataDefinition()
            ->getPropertiesDefinition()
            ->getProperty($this->strName)
            ->getDescription();

        if ($GLOBALS['TL_CONFIG']['showHelp'] && strlen($label)) {
            $result .= '<p class="tl_help tl_tip">' . $label . '</p>';
        }

        echo $result;
        exit;
    }

    /**
     * Retrieve the item container.
     *
     * @return DC_General
     */
    public function getItemContainer()
    {
        return $this->itemContainer;
    }

    /**
     * Retrieve the environment of the item data container.
     *
     * @return EnvironmentInterface
     */
    public function getEnvironment()
    {
        return $this->getItemContainer()->getEnvironment();
    }

    /**
     * Create a tree states instance.
     *
     * @return TreeNodeStates
     */
    public function getTreeNodeStates()
    {
        if (!isset($this->nodeStates)) {
            $environment      = $this->getEnvironment();
            $sessionStorage   = $environment->getSessionStorage();
            $this->nodeStates = new TreeNodeStates(
                $sessionStorage->get($this->getToggleId()),
                $this->determineParentsOfValues()
            );

            // Maybe it is not the best location to do this here.
            if ($environment->getInputProvider()->getParameter('ptg') == 'all') {
                // Save in session and reload.
                $sessionStorage->set(
                    $this->getToggleId(),
                    $this->nodeStates->setAllOpen($this->nodeStates->isAllOpen())->getStates()
                );

                $environment->getEventDispatcher()->dispatch(ContaoEvents::CONTROLLER_RELOAD, new ReloadEvent());
            }
        }

        return $this->nodeStates;
    }

    /**
     * Add specific attributes.
     *
     * @param string $strKey   The key to set.
     *
     * @param mixed  $varValue The value.
     *
     * @return void
     *
     * @throws \RuntimeException When an unknown field type is encountered.
     */
    public function __set($strKey, $varValue)
    {
        switch ($strKey) {
            case 'sourceName':
                $this->sourceName = $varValue;
                break;

            case 'fieldType':
                if ($varValue === 'radio' || $varValue === 'checkbox') {
                    $this->fieldType = $varValue;
                }
                break;

            case 'titleIcon':
                $this->titleIcon = $varValue;
                break;

            case 'mandatory':
                $this->arrConfiguration['mandatory'] = !!$varValue;
                break;

            case 'orderField':
                $this->orderField = $varValue;
                break;

            case 'value':
                switch ($this->fieldType) {
                    case 'radio':
                        if (!empty($varValue) && !is_array($varValue)) {
                            $varValue = array($varValue);
                        }
                        break;
                    case 'checkbox':
                        if (!empty($varValue) && !is_array($varValue)) {
                            $delimiter = (stripos($varValue, "\t") !== false) ? "\t" : ',';
                            $varValue  = trimsplit($delimiter, $varValue);
                        }
                        break;
                    default:
                        throw new \RuntimeException('Unknown field type encountered: ' . $this->fieldType);
                }

                $this->varValue = $varValue;
                break;

            default:
                parent::__set($strKey, $varValue);
                break;
        }
    }

    /**
     * Return an object property.
     *
     * Supported keys:
     *
     * * sourceName: the name of the data container for the item elements.
     * * fieldType : the input type. Either "radio" or "checkbox".
     * * titleIcon:  the icon to use in the title section.
     * * mandatory:  the field value must not be empty.
     *
     * @param string $strKey The property name.
     *
     * @return string The property value.
     */
    public function __get($strKey)
    {
        switch ($strKey) {
            case 'sourceName':
                return $this->sourceName;

            case 'fieldType':
                return $this->fieldType;

            case 'titleIcon':
                return $this->titleIcon;

            case 'mandatory':
                return $this->arrConfiguration['mandatory'];

            case 'orderField':
                return $this->orderField;

            default:
        }
        return parent::__get($strKey);
    }

    /**
     * Skip the field if "change selection" is not checked.
     *
     * @param array $varInput The current value.
     *
     * @return array|string
     */
    protected function validator($varInput)
    {
        if (!($this->Input->post($this->strName . '_save') || $this->alwaysSave)) {
            $this->blnSubmitInput = false;
        }

        return parent::validator($varInput);
    }

    /**
     * Render the current values for listing.
     *
     * @return array
     */
    public function renderItemsPlain()
    {
        $values     = array();
        $value      = $this->varValue;
        $idProperty = $this->idProperty ?: 'id';

        if (is_array($value) && !empty($value)) {
            $environment = $this->getEnvironment();
            $dataDriver  = $environment->getDataProvider();
            $config      = $environment->getBaseConfigRegistry()->getBaseConfig();
            $filter      = FilterBuilder::fromArrayForRoot()
                ->getFilter()
                ->andPropertyValueIn($idProperty, $value)
                ->getAllAsArray();

            $config->setFilter($filter);

            // Set the sort field.
            if ($this->orderField && $dataDriver->fieldExists($this->orderField)) {
                $config->setSorting(array($this->orderField => 'ASC'));
            }

            $collection = $dataDriver->fetchAll($config);
            if ($collection->length() > 0) {
                foreach ($collection as $model) {
                    $formatted        = $this->formatModel($model, false);
                    $idValue          = $model->getProperty($idProperty);
                    $values[$idValue] = $formatted[0]['content'];
                }
            }

            // Apply a custom sort order.
            // TODO: this is untested.
            if ($this->orderField && is_array($this->{$this->strOrderField})) {
                $arrNew = array();

                foreach ($this->{$this->strOrderField} as $i) {
                    if (isset($values[$i])) {
                        $arrNew[$i] = $values[$i];
                        unset($values[$i]);
                    }
                }

                if (!empty($values)) {
                    foreach ($values as $k => $v) {
                        $arrNew[$k] = $v;
                    }
                }

                $values = $arrNew;
                unset($arrNew);
            }
        }

        return $values;
    }

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function generate()
    {
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/dc-general/html/js/vanillaGeneral.js';

        $environment = $this->getEnvironment();
        $translator  = $environment->getTranslator();
        $template    = (TL_MODE === 'BE')
            ? new BackendTemplate('widget_treepicker')
            : new FrontendTemplate('widget_treepicker');

        $icon = new GenerateHtmlEvent($this->titleIcon);
        $environment->getEventDispatcher()->dispatch(ContaoEvents::IMAGE_GET_HTML, $icon);

        $template->id    = $this->strId;
        $template->name  = $this->strName;
        $template->class = ($this->strClass ? ' ' . $this->strClass : '');
        $template->icon  = $icon->getHtml();
        $template->title = $translator->translate(
            $this->title ?: 'MSC.treePicker',
            '',
            array($this->sourceName)
        );

        $template->changeSelection = $translator->translate('MSC.changeSelection');
        $template->dragItemsHint   = $translator->translate('MSC.dragItemsHint');
        $template->fieldType       = $this->fieldType;
        $template->values          = $this->renderItemsPlain();
        $template->popupUrl        = 'system/modules/dc-general/backend/generaltree.php?' .
            sprintf(
                'do=%s&amp;table=%s&amp;field=%s&amp;act=show&amp;id=%s&amp;value=%s&amp;rt=%s',
                \Input::get('do'),
                $this->strTable,
                $this->strField,
                $environment->getInputProvider()->getParameter('id'),
                implode(',', array_keys($template->values)),
                REQUEST_TOKEN
            );

        // Load the fonts for the drag hint.
        $GLOBALS['TL_CONFIG']['loadGoogleFonts'] = true;

        return $template->parse();
    }

    /**
     * Generate when being called from within a popup.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function generatePopup()
    {
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/dc-general/html/js/vanillaGeneral.js';

        $environment = $this->getEnvironment();
        $translator  = $environment->getTranslator();
        $template    = (TL_MODE === 'BE')
            ? new BackendTemplate('widget_treepicker_popup')
            : new FrontendTemplate('widget_treepicker_popup');

        $icon = new GenerateHtmlEvent($this->titleIcon);
        $environment->getEventDispatcher()->dispatch(ContaoEvents::IMAGE_GET_HTML, $icon);

        $template->id    = $this->strId;
        $template->name  = $this->strName;
        $template->class = ($this->strClass ? ' ' . $this->strClass : '');
        $template->icon  = $icon->getHtml();
        $template->title = $translator->translate(
            $this->title ?: 'MSC.treePicker',
            '',
            array($this->sourceName)
        );

        $template->fieldType     = $this->fieldType;
        $template->resetSelected = $translator->translate('MSC.resetSelected');
        $template->selectAll     = $translator->translate('MSC.selectAll');
        $template->values        = deserialize($this->varValue, true);

        // Get table, column and setup root id's.
        $root = $this->root;
        $root = is_array($root) ? $root : ((is_numeric($root) && $root > 0) ? array($root) : array());

        // Create Tree Render with custom root points.
        $tree = '';
        foreach (array_merge($root, array(0)) as $pid) {
            $collection = $this->loadCollection($pid);
            $tree      .= $this->generateTreeView($collection, 'tree');
        }

        $template->tree = $tree;

        // Load the fonts for the drag hint.
        $GLOBALS['TL_CONFIG']['loadGoogleFonts'] = true;

        return $template->parse();
    }

    /**
     * Generate a particular sub part of the page tree and return it as HTML string.
     *
     * @return void
     */
    public function generateAjax()
    {
        $environment = $this->getEnvironment();
        $input       = $environment->getInputProvider();

        if ($input->hasValue('action')
            && $input->getValue('action') === 'DcGeneralLoadSubTree'
        ) {
            $provider = $input->getValue('providerName');
            $rootId   = $input->getValue('id');
            $this->getEnvironment()->getSessionStorage()->set(
                $this->getToggleId(),
                $this->getTreeNodeStates()->toggleModel($provider, $rootId)->getStates()
            );
            $collection = $this->loadCollection($rootId, (intval($input->getValue('level')) + 1));
            echo $this->generateTreeView($collection, 'tree');
            exit;
        }
    }

    /**
     * Retrieve the id for this view.
     *
     * @return string
     */
    protected function getToggleId()
    {
        return $this->getEnvironment()->getDataDefinition()->getName() . $this->strId . '_tree';
    }

    /**
     * Retrieve the id for this view.
     *
     * @return string
     */
    public function getSearchSessionKey()
    {
        return $this->getEnvironment()->getDataDefinition()->getName() . $this->strId . '_tree_search';
    }

    /**
     * Check if an custom sorting field has been defined.
     *
     * @return bool
     */
    public function isSearchAvailable()
    {
        return !empty($this->searchField);
    }

    /**
     * Check the state of a model and set the metadata accordingly.
     *
     * @param ModelInterface $model The model of which the state shall be checked of.
     *
     * @param int            $level The tree level the model is contained within.
     *
     * @return void
     */
    protected function determineModelState(ModelInterface $model, $level)
    {
        $model->setMeta(DCGE::TREE_VIEW_LEVEL, $level);
        $model->setMeta(
            DCGE::TREE_VIEW_IS_OPEN,
            $this->getTreeNodeStates()->isModelOpen(
                $model->getProviderName(),
                $model->getId()
            )
        );
    }

    /**
     * This "renders" a model for tree view.
     *
     * @param ModelInterface $objModel     The model to render.
     *
     * @param int            $intLevel     The current level in the tree hierarchy.
     *
     * @param array          $arrSubTables The names of data providers that shall be rendered "below" this item.
     *
     * @return void
     */
    protected function treeWalkModel(ModelInterface $objModel, $intLevel, $arrSubTables = array())
    {
        $relationships = $this->getEnvironment()->getDataDefinition()->getModelRelationshipDefinition();
        $blnHasChild   = false;

        $this->determineModelState($objModel, ($intLevel - 1));

        $arrChildCollections = array();
        foreach ($arrSubTables as $strSubTable) {
            // Evaluate the child filter for this item.
            $arrChildFilter = $relationships->getChildCondition($objModel->getProviderName(), $strSubTable);

            // If we do not know how to render this table within here, continue with the next one.
            if (!$arrChildFilter) {
                continue;
            }

            // Create a new Config and fetch the children from the child provider.
            $objChildConfig = $this->getEnvironment()->getDataProvider($strSubTable)->getEmptyConfig();
            $objChildConfig->setFilter($arrChildFilter->getFilter($objModel));

            // TODO: hardcoded sorting... NOT GOOD!
            $objChildConfig->setSorting(array('sorting' => 'ASC'));

            $objChildCollection = $this->getEnvironment()->getDataProvider($strSubTable)->fetchAll($objChildConfig);

            $blnHasChild = ($objChildCollection->length() > 0);

            // Speed up - we may exit if we have at least one child but the parenting model is collapsed.
            if ($blnHasChild && !$objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN)) {
                break;
            } elseif ($blnHasChild) {
                foreach ($objChildCollection as $objChildModel) {
                    // Let the child know about it's parent.
                    $objModel->setMeta($objModel::PARENT_ID, $objModel->getID());
                    $objModel->setMeta($objModel::PARENT_PROVIDER_NAME, $objModel->getProviderName());

                    $mySubTables = array();
                    foreach ($relationships->getChildConditions($objModel->getProviderName()) as $condition) {
                        $mySubTables[] = $condition->getDestinationName();
                    }

                    $this->treeWalkModel($objChildModel, ($intLevel + 1), $mySubTables);
                }
                $arrChildCollections[] = $objChildCollection;

                // Speed up, if collapsed, one item is enough to break as we have some children.
                if (!$objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN)) {
                    break;
                }
            }
        }

        // If expanded, store children.
        if ($objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN) && count($arrChildCollections) != 0) {
            $objModel->setMeta(DCGE::TREE_VIEW_CHILD_COLLECTION, $arrChildCollections);
        }

        $objModel->setMeta($objModel::HAS_CHILDREN, $blnHasChild);
    }

    /**
     * Recursively retrieve a collection of all complete node hierarchy.
     *
     * @param array  $rootId       The ids of the root node.
     *
     * @param int    $intLevel     The level the items are residing on.
     *
     * @param string $providerName The data provider from which the root element originates from.
     *
     * @return CollectionInterface
     */
    public function getTreeCollectionRecursive($rootId, $intLevel = 0, $providerName = null)
    {
        $environment      = $this->getEnvironment();
        $definition       = $environment->getDataDefinition();
        $dataDriver       = $environment->getDataProvider($providerName);
        $objTableTreeData = $dataDriver->getEmptyCollection();
        $objRootConfig    = $environment->getBaseConfigRegistry()->getBaseConfig();
        $relationships    = $definition->getModelRelationshipDefinition();

        if (!$rootId) {
            $objRootCondition = $definition->getModelRelationshipDefinition()->getRootCondition();

            if ($objRootCondition) {
                $arrBaseFilter = $objRootConfig->getFilter();
                $arrFilter     = $objRootCondition->getFilterArray();

                if ($arrBaseFilter) {
                    $arrFilter = array_merge($arrBaseFilter, $arrFilter);
                }

                $objRootConfig->setFilter($arrFilter);
            }
            // Fetch all root elements.
            $objRootCollection = $dataDriver->fetchAll($objRootConfig);

            if ($objRootCollection->length() > 0) {
                $mySubTables = array();
                foreach ($relationships->getChildConditions(
                    $objRootCollection->get(0)->getProviderName()
                ) as $condition) {
                    $mySubTables[] = $condition->getDestinationName();
                }

                foreach ($objRootCollection as $objRootModel) {
                    /** @var ModelInterface $objRootModel */
                    $objTableTreeData->push($objRootModel);
                    $this->treeWalkModel($objRootModel, ($intLevel + 1), $mySubTables);
                }
            }

            return $objTableTreeData;
        }

        $objRootConfig->setId($rootId);
        // Fetch root element.
        $objRootModel = $dataDriver->fetch($objRootConfig);

        $mySubTables = array();
        foreach ($relationships->getChildConditions($objRootModel->getProviderName()) as $condition) {
            $mySubTables[] = $condition->getDestinationName();
        }

        $this->treeWalkModel($objRootModel, $intLevel, $mySubTables);
        $objRootCollection = $dataDriver->getEmptyCollection();
        $objRootCollection->push($objRootModel);

        return $objRootCollection;
    }

    /**
     * Load the collection of child items and the parent item for the currently selected parent item.
     *
     * @param mixed $rootId       The root element (or null to fetch everything).
     *
     * @param int   $intLevel     The current level in the tree (of the optional root element).
     *
     * @param null  $providerName The data provider from which the optional root element shall be taken from.
     *
     * @return CollectionInterface
     */
    public function loadCollection($rootId = null, $intLevel = 0, $providerName = null)
    {
        $environment = $this->getEnvironment();
        $dataDriver  = $environment->getDataProvider($providerName);

        $objCollection = $this->getTreeCollectionRecursive($rootId, $intLevel, $providerName);

        if ($rootId) {
            $objTreeData = $dataDriver->getEmptyCollection();
            $objModel    = $objCollection->get(0);
            foreach ($objModel->getMeta(DCGE::TREE_VIEW_CHILD_COLLECTION) as $objCollection) {
                foreach ($objCollection as $objSubModel) {
                    $objTreeData->push($objSubModel);
                }
            }
            return $objTreeData;
        }

        return $objCollection;
    }

    /**
     * Retrieve the formatter for the given model.
     *
     * @param ModelInterface $model    The model for which the formatter shall be retrieved.
     * @param bool           $treeMode Flag if we are running in tree mode or not.
     *
     * @return ModelFormatterConfigInterface
     */
    protected function getFormatter(ModelInterface $model, $treeMode)
    {
        /** @var ListingConfigInterface $listing */
        $definition = $this->getEnvironment()->getDataDefinition();
        $listing    = $definition->getDefinition(Contao2BackendViewDefinitionInterface::NAME)->getListingConfig();

        if ($listing->hasLabelFormatter($model->getProviderName())) {
            return $listing->getLabelFormatter($model->getProviderName());
        }

        // If not in tree mode and custom label has been defined, use it.
        if (!$treeMode && $this->itemLabel) {
            $label     = $this->itemLabel;
            $formatter = new DefaultModelFormatterConfig();
            $formatter->setPropertyNames($label['fields']);
            $formatter->setFormat($label['format']);
            $formatter->setMaxLength($label['maxCharacters']);
            return $formatter;
        }

        // If no label has been defined, use some default.
        $properties = array();
        foreach ($definition->getPropertiesDefinition()->getProperties() as $property) {
            if ($property->getWidgetType() == 'text') {
                $properties[] = $property->getName();
            }
        }

        $formatter = new DefaultModelFormatterConfig();
        $formatter->setPropertyNames($properties);
        $formatter->setFormat(str_repeat('%s ', count($properties)));

        return $formatter;
    }

    /**
     * Format a model accordingly to the current configuration.
     *
     * Returns either an array when in tree mode or a string in (parented) list mode.
     *
     * @param ModelInterface $model    The model that shall be formatted.
     *
     * @param bool           $treeMode Flag if we are running in tree mode or not (optional, default: true).
     *
     * @return array
     */
    public function formatModel(ModelInterface $model, $treeMode = true)
    {
        /** @var ListingConfigInterface $listing */
        $definition   = $this->getEnvironment()->getDataDefinition();
        $listing      = $definition->getDefinition(Contao2BackendViewDefinitionInterface::NAME)->getListingConfig();
        $properties   = $definition->getPropertiesDefinition();
        $sorting      = array_keys((array) $listing->getDefaultSortingFields());
        $firstSorting = reset($sorting);
        $formatter    = $this->getFormatter($model, $treeMode);

        $args = array();
        foreach ($formatter->getPropertyNames() as $propertyName) {
            if ($properties->hasProperty($propertyName)) {
                $args[$propertyName] = (string) $model->getProperty($propertyName);
            } else {
                $args[$propertyName] = '-';
            }
        }

        $event = new ModelToLabelEvent($this->getEnvironment(), $model);
        $event
            ->setArgs($args)
            ->setLabel($formatter->getFormat())
            ->setFormatter($formatter);

        $this->getEnvironment()->getEventDispatcher()->dispatch(
            sprintf('%s[%s]', $event::NAME, $definition->getName()),
            $event
        );
        $this->getEnvironment()->getEventDispatcher()->dispatch($event::NAME, $event);

        $arrLabel = array();

        // Add columns.
        if ($listing->getShowColumns()) {
            $fields = $formatter->getPropertyNames();
            $args   = $event->getArgs();

            if (!is_array($args)) {
                $arrLabel[] = array(
                    'colspan' => count($fields),
                    'class'   => 'tl_file_list col_1',
                    'content' => $args
                );
            } else {
                foreach ($fields as $j => $propertyName) {
                    $arrLabel[] = array(
                        'colspan' => 1,
                        'class'   => 'tl_file_list col_' . $j . (($propertyName == $firstSorting) ? ' ordered_by' : ''),
                        'content' => (($args[$propertyName] != '') ? $args[$propertyName] : '-')
                    );
                }
            }
        } else {
            if (!is_array($event->getArgs())) {
                $string = $event->getArgs();
            } else {
                $string = vsprintf($event->getLabel(), $event->getArgs());
            }

            if ($formatter->getMaxLength() !== null && strlen($string) > $formatter->getMaxLength()) {
                $string = substr($string, 0, $formatter->getMaxLength());
            }

            $arrLabel[] = array(
                'colspan' => null,
                'class'   => 'tl_file_list',
                'content' => $string
            );
        }

        return $arrLabel;
    }

    /**
     * Render a given model.
     *
     * @param ModelInterface $objModel    The model to render.
     *
     * @param string         $strToggleID The id of the toggler.
     *
     * @return string
     */
    protected function parseModel($objModel, $strToggleID)
    {
        $objModel->setMeta($objModel::LABEL_VALUE, $this->formatModel($objModel));

        $objTemplate = (TL_MODE === 'BE')
            ? new BackendTemplate('widget_treepicker_entry')
            : new FrontendTemplate('widget_treepicker_entry');

        if ($objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN)) {
            $toggleTitle = $this->getEnvironment()->getTranslator()->translate('collapseNode', 'MSC');
        } else {
            $toggleTitle = $this->getEnvironment()->getTranslator()->translate('expandNode', 'MSC');
        }

        $toggleScript = sprintf(
            'Backend.getScrollOffset(); return BackendGeneral.loadSubTree(this, ' .
            '{\'toggler\':\'%s\', \'id\':\'%s\', \'providerName\':\'%s\', \'level\':\'%s\'});',
            $strToggleID,
            $objModel->getId(),
            $objModel->getProviderName(),
            $objModel->getMeta('dc_gen_tv_level')
        );

        $toggleUrlEvent = new AddToUrlEvent(
            'ptg=' . $objModel->getId() . '&amp;provider=' . $objModel->getProviderName()
        );
        $this->getEnvironment()->getEventDispatcher()->dispatch(ContaoEvents::BACKEND_ADD_TO_URL, $toggleUrlEvent);

        $objTemplate->id           = $this->strId;
        $objTemplate->name         = $this->strName;
        $objTemplate->fieldType    = $this->fieldType;
        $objTemplate->environment  = $this->getEnvironment();
        $objTemplate->objModel     = $objModel;
        $objTemplate->strToggleID  = $strToggleID;
        $objTemplate->toggleUrl    = $toggleUrlEvent->getUrl();
        $objTemplate->toggleTitle  = $toggleTitle;
        $objTemplate->toggleScript = $toggleScript;
        $objTemplate->active       = $this->optionChecked($objModel->getProperty($this->idProperty), $this->value);
        $objTemplate->idProperty   = $this->idProperty;

        return $objTemplate->parse();
    }

    /**
     * Generate the tree view for a given collection.
     *
     * @param CollectionInterface $objCollection The collection to iterate over.
     *
     * @param string              $treeClass     The class to use for the tree.
     *
     * @return string
     */
    protected function generateTreeView($objCollection, $treeClass)
    {
        $arrHtml = array();

        foreach ($objCollection as $objModel) {
            /** @var ModelInterface $objModel */

            $strToggleID = $objModel->getProviderName() . '_' . $treeClass . '_' . $objModel->getID();

            $arrHtml[] = $this->parseModel($objModel, $strToggleID);

            if ($objModel->getMeta($objModel::HAS_CHILDREN) && $objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN)) {
                $objTemplate = (TL_MODE === 'BE')
                    ? new BackendTemplate('widget_treepicker_child')
                    : new FrontendTemplate('widget_treepicker_child');

                $strSubHtml = '';

                foreach ($objModel->getMeta(DCGE::TREE_VIEW_CHILD_COLLECTION) as $objCollection) {
                    $strSubHtml .= $this->generateTreeView($objCollection, $treeClass);
                }

                $objTemplate->objParentModel = $objModel;
                $objTemplate->strToggleID    = $strToggleID;
                $objTemplate->strHTML        = $strSubHtml;
                $objTemplate->strTable       = $objModel->getProviderName();

                $arrHtml[] = $objTemplate->parse();
            }
        }

        return implode("\n", $arrHtml);
    }


    /**
     * Fetch all parents of the passed model.
     *
     * @param ModelInterface $value   The model.
     *
     * @param string[]       $parents The ids of all detected parents so far.
     *
     * @return void
     */
    private function parentsOf($value, &$parents)
    {
        $controller = $this->getEnvironment()->getController();
        if (!$controller->isRootModel($value)) {
            $parent = $controller->searchParentOf($value);
            if (!isset($parents[$value->getProviderName()][$parent->getId()])) {
                $this->parentsOf($parent, $parents);
            }
        }

        $parents[$value->getProviderName()][$value->getId()] = 1;
    }

    /**
     * Determine all parents of all selected values.
     *
     * @return array
     */
    private function determineParentsOfValues()
    {
        $parents     = array();
        $environment = $this->getEnvironment();

        foreach ((array) $this->varValue as $value) {
            $dataDriver = $environment->getDataProvider();
            $this->parentsOf($dataDriver->fetch($dataDriver->getEmptyConfig()->setId($value)), $parents);
        }

        return $parents;
    }
}
