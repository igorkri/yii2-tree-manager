<?php

/**
 * @copyright Copyright &copy; Kartik Visweswaran, Krajee.com, 2015 - 2022
 * @package   yii2-tree-manager
 * @version   1.1.3
 */

namespace igorkri\tree;

use Closure;
use Exception;
use kartik\base\Config;
use kartik\base\Lib;
use kartik\base\Widget;
use kartik\dialog\Dialog;
use igorkri\tree\models\Tree;
use Yii;
use yii\base\InvalidConfigException;
use kartik\base\PluginAssetBundle;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * TreeView is an enhanced tree view widget for Yii Framework 2 that allows management and manipulation of hierarchical
 * data using nested sets. The widget utilizes the [yii2-nested-sets](https://github.com/creocoder/yii2-nested-sets) extension to
 * manage the tree structure in your database.
 *
 * For example,
 *
 * ```php
 * use igorkri\tree\TreeView;
 * echo TreeView::widget([
 *     // single query fetch to render the tree
 *     'query'             => Tree::find()->addOrderBy('root, lft'),
 *     'headingOptions'    => ['label' => 'Categories'],
 *     'isAdmin'           => false,                       // optional (toggle to enable admin mode)
 *     'displayValue'      => 1,                           // initial display value
 *     //'softDelete'      => true,                        // normally not needed to change
 *     //'cacheSettings'   => ['enableCache' => true]      // normally not needed to change
 * ]);
 * ```
 *
 * @see https://github.com/creocoder/yii2-nested-sets
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 */
class TreeView extends Widget
{
    /**
     * Value for root node key
     */
    const ROOT_KEY = 'KRAJEE~!~ROOT~!~NODE';
    /**
     * Create root tree node button
     */
    const BTN_CREATE_ROOT = 'create-root';
    /**
     * Create tree node button
     */
    const BTN_CREATE = 'create';
    /**
     * Remove tree node button
     */
    const BTN_REMOVE = 'remove';
    /**
     * Move tree node up button
     */
    const BTN_MOVE_UP = 'move-up';
    /**
     * Move tree node down button
     */
    const BTN_MOVE_DOWN = 'move-down';
    /**
     * Move tree node left button
     */
    const BTN_MOVE_LEFT = 'move-left';
    /**
     * Move tree node right button
     */
    const BTN_MOVE_RIGHT = 'move-right';
    /**
     * Tree refresh button
     */
    const BTN_REFRESH = 'refresh';
    /**
     * Button separator
     */
    const BTN_SEPARATOR = 'separator';
    /**
     * CSS class based icon
     */
    const ICON_CSS = 1;
    /**
     * Raw HTML markup based icon
     */
    const ICON_RAW = 2;
    /**
     * Direction for moving selected node UP one level in the tree
     */
    const MOVE_UP = 'u';
    /**
     * Direction for moving selected node DOWN one level in the tree
     */
    const MOVE_DOWN = 'd';
    /**
     * Direction for moving selected node LEFT one level in the tree
     */
    const MOVE_LEFT = 'l';
    /**
     * Direction for moving selected node RIGHT one level in the tree
     */
    const MOVE_RIGHT = 'r';
    /**
     * @var array the actions for managing, deleting, and moving the tree nodes. The keys must be one of 'manage',
     * 'save', 'remove', and 'move'. Defaults to:
     * ```
     * [
     *     'save' => Url::to(['/treemanager/node/save']),
     *     'manage' => Url::to(['/treemanager/node/manage']),
     *     'remove' => Url::to(['/treemanager/node/remove']),
     *     'move' => Url::to(['/treemanager/node/move']),
     * ]
     * ```
     */
    public $nodeActions = [];

    /**
     * @var string the title label to display for the tree node in the validation client messages.
     * Defaults to `node`.
     */
    public $nodeTitle;

    /**
     * @var string the title label to display for plural tree nodes in the validation client messages.
     * Defaults to `nodes`.
     */
    public $nodeTitlePlural;

    /**
     * @var array|Closure the value to customize a tree node's label.
     * - if set as an array, the array key must be the node key value (i.e. `keyAttribute`) and array value will be the
     *  new node label you want to assign.
     * - if set as a Closure, the callback function must be of the signature `function($node){}`, where `$node` will
     *  represent each tree node's model object instance.
     * If a value is not traceable through above methods, the database tree node name will be displayed (as parsed via
     * `nameAttribute`).
     */
    public $nodeLabel;

    /**
     * @var string the view file that will render the form for editing the node.
     */
    public $nodeView;

    /**
     * @var array the markup for the submit and reset button labels in the node view form
     */
    public $nodeViewButtonLabels = ['submit' => null, 'reset' => null];

    /**
     * @var array any additional parameters to be sent or any parameters to override within the [[nodeView]].
     */
    public $nodeViewParams = [];

    /**
     * @var array the list of additional view files that will be used to append content at various sections in the
     * [[nodeView]] form.
     */
    public $nodeAddlViews = [];

    /**
     * @var ActiveQuery the query that will be used as the data source for the TreeView. For example:
     * `Tree::find()->addOrderBy('root, lft')`
     */
    public $query;

    /**
     * @var integer the initial value (key) to be selected in the tree and displayed in the detail form. Defaults to 1.
     */
    public $displayValue = 1;

    /**
     * @var array the HTML attributes for the node detail form.
     */
    public $nodeFormOptions = [];

    /**
     * @var array the breadcrumbs settings for displaying the current node title based on parent hierarchy in the node
     * details form/view (starting from the current node). The following settings are supported:
     * - `depth`: _integer_, the depth to dig into the parent nodes for fetching the breadcrumb titles.  If set to `null` or
     *  `0` this will fetch breadcrumbs till infinite parent depth. Defaults to ``.
     * - `glue`: _string_, the separator to glue each node name within the breadcrumbs. Defaults to ` &rsaquo; `.
     * - `activeCss`: _string_, the CSS class to be applied to the current node name in the breadcrumbs. Defaults to
     *   `kv-crumb-active`.
     * - `untitled`: _string_, the title to be displayed if this is a new untitled node record. Defaults to `Untitled`.
     */
    public $breadcrumbs = [];

    /**
     * @var string the comma separated initial value (keys) to be checked and selected in the tree
     */
    public $value = '';

    /**
     * @var string message shown on tree initialization when either the entire tree is empty or no node is found for
     * the selected [[displayValue]].
     */
    public $emptyNodeMsg;

    /**
     * @var array HTML attributes for the empty node message displayed.
     */
    public $emptyNodeMsgOptions = ['class' => 'kv-node-message'];

    /**
     * @var boolean whether to show the key attribute (ID) in the node details form/view.
     */
    public $showIDAttribute = true;

    /**
     * @var boolean whether to show the name attribute (name) in the node details form/view.
     */
    public $showNameAttribute = true;

    /**
     * @var boolean whether to show the form action buttons in the node details form/view.
     */
    public $showFormButtons = true;

    /**
     * @var boolean whether the tree is to be allowed for editing in admin mode. This will display all nodes and will
     * allow to modify internal tree node flags. Defaults to `false`.
     */
    public $isAdmin = false;

    /**
     * @var boolean whether the record will be soft deleted, when remove button is clicked. Defaults to `true`. The
     * following actions are possible:
     * - if `true`, this will just set the `active` property of node to `false`.
     * - if `false`, it will attempt to hard delete the whole record.
     */
    public $softDelete = true;

    /**
     * @var boolean whether to show a checkbox before each tree node label to allow multiple node selection.
     */
    public $showCheckbox = false;

    /**
     * @var boolean whether to allow selection of multiple nodes via checkboxes.
     */
    public $multiple = true;

    /**
     * @var boolean whether to auto select all children when a parent node is selected. This property will be applied
     * only if [[multiple]] property is set to `true`.
     */
    public $cascadeSelectChildren = true;

    /**
     * @var integer animation duration (ms) for fading in and out alerts that are displayed during manipulation of nodes.
     */
    public $alertFadeDuration = 1000;

    /**
     * @var array cache settings for displaying the detail form content for each tree node via ajax. The following
     * options are supported:
     * - `enableCache`: _boolean_, defaults to `true`.
     * - `cacheTimeout`: _integer_, the cache timeout in milliseconds. Defaults to `300000` (or `5 minutes`).
     */
    public $cacheSettings = [];

    /**
     * @var boolean whether to show inactive nodes
     */
    public $showInactive = false;

    /**
     * @var boolean whether to use font awesome icons. Defaults to `false`.
     * @deprecated since v1.1.3 (for Bootstrap 4.x and above this will always be true)
     */
    public $fontAwesome = false;

    /**
     * @var array settings to edit the icon. The following settings are recognized:
     * - `show`: _string_, whether to display the icons selection as a list. If set to 'text', the icon will be shown as a
     *   plain text input along with icon type. If set to `'list'`, a list will be shown. If set to `'none'`, then no
     *   icon settings will be shown for editing.
     * - `type`: _string_, the iconTypeAttribute value, defaults to TreeView::ICON_CSS. Should be one of [[ICON_CSS]]
     *   or [[ICON_RAW]].
     * - `listData`: _array_, the configuration of the icon list data to be shown for selection. This is mandatory if you
     *   set `show` to 'list'. You must set the data as `$key => $value` format. The list will be parsed to display
     *   the icon list and will depend on the `type`.
     *   - If `type` is set to [[ICON_CSS]], then `$key` will be the icon suffix name and `$value` will be the description
     *     for the icon. The icon markup will be automatically parsed then based on whether its a glyphicon or font-awesome
     *     when `fontAwesome` property is `true`. For example:
     *     ```php
     *          [
     *              'folder-close' => 'Folder',
     *              'file' => 'File',
     *              'tag' => 'Tag'
     *          ]
     *      ```
     *   - If `type` is set to [[ICON_RAW]]  `$key` is the icon markup to be stored and `$value` is the output markup to
     *     be displayed as a selection in the list. For example:
     *     ```php
     *          [
     *              '<img src="images/folder.jpg">' => 'Folder',
     *              '<img src="images/file.jpg">' => 'File',
     *              '<img src="images/tag.jpg">' => 'Tag',
     *          ]
     *     ```
     */
    public $iconEditSettings = [
        'show' => 'text',
        'type' => self::ICON_CSS,
        'listData' => [],
    ];

    /**
     * @var array the settings for the tree management toolbar
     */
    public $toolbar = [];

    /**
     * @var array the HTML attributes for the toolbar.
     */
    public $toolbarOptions = [];

    /**
     * @var array the sorting order of the buttons in the toolbar. Each item in this array should be one of the button
     * keys as set in the `toolbar` array keys (except for BTN_SEPARATOR which will be parsed as is). Note that,
     * if this is set, then only the button keys configured here will be displayed (irrespective of the toolbar
     * setup). Hence one must ensure that all the toolbar button keys are set here to display all the toolbar
     * buttons.
     */
    public $toolbarOrder = [];

    /**
     * @var array the HTML attributes for the button groups within the toolbar.
     */
    public $buttonGroupOptions = ['class' => 'btn-group-sm'];

    /**
     * @var array the default HTML attributes for the toolbar buttons
     */
    public $buttonOptions = [];

    /**
     * @var array the default HTML attributes for the toolbar button icons
     */
    public $buttonIconOptions = [];

    /**
     * @var boolean show toolbar button tooltips (using bootstrap tooltip plugin). The `BootstrapPluginAsset` will
     * automatically be loaded if this is set to `true`.
     */
    public $showTooltips = true;

    /**
     * @var boolean whether to auto load the bootstrap plugin assets if `showTooltips` is `true` OR if
     * `TreeViewInput::asDropdown` is true. Defaults to `true`.
     */
    public $autoLoadBsPlugin = true;

    /**
     * @var string the icon markup for the child node if no icon was setup in the database.
     */
    public $defaultChildNodeIcon;

    /**
     * @var string the icon markup for the collapsed parent node if no icon was setup in the database.
     */
    public $defaultParentNodeIcon;

    /**
     * @var string the icon markup for the opened parent node if no icon was setup in the database. If not set will
     * default to:
     *    - `<i class="fas fa-plus-square"></i>` if [[bsVersion]] is not `3.x` or [[fontAwesome]] is `true`.
     *    - `<i class="glyphicon glyphicon-expand"></i>` if [[bsVersion]] is `3.x` and [[fontAwesome]] is `false` .
     */
    public $defaultParentNodeOpenIcon;

    /**
     * @var string the default icon for expanding the node. If not set will default to:
     *    - `<i class="fas fa-plus-square"></i>` if [[bsVersion]] is not `3.x` or [[fontAwesome]] is `true`.
     *    - `<i class="glyphicon glyphicon-expand"></i>` if [[bsVersion]] is `3.x` and [[fontAwesome]] is `false` .
     */
    public $defaultExpandNodeIcon;

    /**
     * @var string the default icon for collapsing the node. If not set will default to:
     *    - `<i class="fas fa-minus-square"></i>` if [[bsVersion]] is not `3.x` or [[fontAwesome]] is `true`.
     *    - `<i class="glyphicon glyphicon-collapse-down"></i>` if [[bsVersion]] is `3.x` and [[fontAwesome]] is `false` .
     */
    public $defaultCollapseNodeIcon;

    /**
     * @var array default icon for a checked node which will represent a checked checkbox. If not set will default to:
     *    - `<i class="fas fa-check-square"></i>` if [[bsVersion]] is not `3.x` or [[fontAwesome]] is `true`.
     *    - `<i class="glyphicon glyphicon-checked"></i>` if [[bsVersion]] is `3.x` and [[fontAwesome]] is `false` .
     */
    public $defaultCheckedNodeIcon;

    /**
     * @var string the HTML attributes for an unchecked node which will represent an unchecked checkbox. If not set will default to:
     *    - `<i class="fas fa-square"></i>` if [[bsVersion]] is not `3.x` or [[fontAwesome]] is `true`.
     *    - `<i class="glyphicon glyphicon-unchecked"></i>` if [[bsVersion]] is `3.x` and [[fontAwesome]] is `false` .
     */
    public $defaultUncheckedNodeIcon;

    /**
     * @var array the HTML attributes for the child node icon.
     */
    public $childNodeIconOptions = ['class' => 'text-info'];

    /**
     * @var array the HTML attributes for the parent node icon.
     */
    public $parentNodeIconOptions = ['class' => 'text-warning'];

    /**
     * @var boolean allow new root creation.
     */
    public $allowNewRoots = true;

    /**
     * @var array the configuration of various client alert messages
     */
    public $clientMessages = [];

    /**
     * @var array the HTML attributes for the topmost root node container. The following special options are recognized:
     * - `label`: _string_, the label for the topmost root node (this is not HTML encoded). Defaults to 'Root'. Set this
     *   to empty to not display a label.
     */
    public $rootOptions = ['class' => 'text-primary'];

    /**
     * @var array the HTML attributes for the root node's toggle indicator
     */
    public $rootNodeToggleOptions = ['class' => 'text-muted'];

    /**
     * @var array the HTML attributes for the root node's checkbox indicator
     */
    public $rootNodeCheckboxOptions = ['class' => 'text-success'];

    /**
     * @var boolean whether to hide the topmost root node container
     */
    public $hideTopRoot = false;

    /**
     * @var boolean whether to show topmost root node as heading. In this case the [[rootOptions]] will be used to
     * format the topmost root node and [[headingOptions]] will be skipped. The CSS class `kv-root-heading` will
     * be appended to `rootOptions` when this is set to `true`.
     */
    public $topRootAsHeading = false;

    /**
     * @var array the HTML attributes for the node toggle indicator for each parent item in the tree
     */
    public $nodeToggleOptions = ['class' => 'text-muted'];

    /**
     * @var array the HTML attributes for the node checkbox indicator for all items in the tree
     */
    public $nodeCheckboxOptions = ['class' => 'text-success'];

    /**
     * @var array the HTML attributes for the indicator for expanding a node. The following special options are
     * recognized:
     * - `label`: _string_, the label for the indicator. If not set will default to [[defaultExpandNodeIcon]].
     */
    public $expandNodeOptions = [];

    /**
     * @var array the HTML attributes for the indicator for collapsing a node. The following special options are
     * recognized:
     * - `label`: _string_, the label for the indicator. If not set will default to [[defaultCollapseNodeIcon]]
     */
    public $collapseNodeOptions = [];

    /**
     * @var array the HTML attributes for the indicator which will represent a checked checkbox. The following special
     * options are recognized:
     * - `label`: _string_, the label for the indicator. If not set will default to [[defaultCheckedNodeIcon]].
     */
    public $checkedNodeOptions = [];

    /**
     * @var array the HTML attributes for the indicator which will represent an unchecked checkbox. The
     * following special options are recognized:
     * - `label`: _string_, the label for the indicator. If not set will default to [[defaultUncheckedNodeIcon]]
     */
    public $uncheckedNodeOptions = [];

    /**
     * @var array the HTML attributes for the wrapper container for the tree header, body, and footer.
     */
    public $treeWrapperOptions = ['class' => 'form-control'];

    /**
     * @var array the HTML attributes for the heading. The following additional option is recognized:
     * `label`: _string_, the label to display for the heading
     * Note that when [[topRootAsHeading]] is set to true, then [[headingOptions]] will be entirely skipped
     * and [[rootOptions]] and [[rootNodeToggleOptions]] and [[rootNodeCheckboxOptions]] will be used to render
     * the heading.
     */
    public $headingOptions = ['class' => 'kv-tree-heading'];

    /**
     * @var array the HTML attributes for the tree header container
     */
    public $headerOptions = [];

    /**
     * @var array the HTML attributes for the search container
     */
    public $searchContainerOptions = [];

    /**
     * @var array the HTML attributes for the search input
     */
    public $searchOptions = ['class' => 'form-control input-sm'];

    /**
     * @var array the HTML attributes for the search clear indicator
     */
    public $searchClearOptions = [];

    /**
     * @var boolean whether to hide unmatched search items when searching
     */
    public $hideUnmatchedSearchItems = true;

    /**
     * @var array the HTML attributes for the tree footer container.
     */
    public $footerOptions = [];

    /**
     * @var array the HTML attributes for the tree selector container
     */
    public $treeOptions = ['style' => 'height:410px'];

    /**
     * @var string the CSS class to hide HTML elements on the page. If not set this will default to one of the following
     * depending on Bootstrap Version:
     * - if [[bsVersion]] = 3, then this will default to `hidden_xs`
     * - if [[bsVersion]] = 4, then this will default to `d-none`
     */
    public $hideCssClass;

    /**
     * @var array the HTML attributes for the detail form container which will display the details of the selected node
     */
    public $detailOptions = [];

    /**
     * @var array the HTML attributes for the input that will store the selected nodes for the widget
     */
    public $options = [];

    /**
     * @var array configuration settings for the Krajee dialog widget that will be used to render alerts and
     * confirmation dialog prompts
     * @see http://demos.krajee.com/dialog
     */
    public $krajeeDialogSettings = [];

    /**
     * @var string the main template for rendering the tree view navigation widget and the node detail view form. The
     * following tokens will be automatically parsed and replaced:
     * - `{wrapper}`: will be replaced with the output markup parsed from [[wrapperTemplate]]
     * - `{detail}`: will be replaced with the the markup for the detail form to edit/view the selected tree node
     */
    public $mainTemplate = <<< HTML
<div class="row">
    <div class="col-sm-3">
        {wrapper}
    </div>
    <div class="col-sm-9">
        {detail}
    </div>
</div>
HTML;

    /**
     * @var string the template for rendering the header
     */
    public $headerTemplate = <<< HTML
<div class="row">
<!--    <div class="col-sm-6">-->
<!--        {heading}-->
<!--    </div>-->
    <div class="col-sm-12">
        {search}
    </div>
</div>
HTML;

    /**
     * @var string the wrapper template for rendering the tree view navigation widget. The following tokens will be
     * automatically parsed and replaced:
     * - `{header}`: will be replaced with the output markup parsed from [[headerTemplate]].
     * - `{tree}`: will be replaced with the markup for the tree hierarchy.
     * - `{footer}`: will be replaced with the output markup parsed from [[footerTemplate]].
     */
    public $wrapperTemplate = "{header}\n{tree}{footer}";

    /**
     * @var string the template for rendering the footer.  The following tokens will be automatically parsed and
     * replaced:
     * - `{toolbar}`: will be replaced with the the markup for the button actions toolbar.
     */
    public $footerTemplate = "{toolbar}";

    /**
     * @var string the icon prefix
     */
    public $iconPrefix;

    /**
     * @var Module the tree management module.
     */
    protected $_module;

    /**
     * @var mixed the icons list
     */
    protected $_nodeIconsList;

    /**
     * @var array the queried tree nodes
     */
    protected $_nodes = [];

    /**
     * @var boolean whether to load the bootstrap plugin asset
     */
    protected $_hasBootstrap = false;

    /**
     * @var string session identifier for the selected node id
     */
    protected $_nodeSelected = null;

    /**
     * @var array configuration of icons for Glyphicons and Font Awesome. The 3rd array value is optional and includes any
     * additional css class to be added.
     */
    protected static $_nodeIcons = [
        'defaultExpandNodeIcon' => ['glyphicon glyphicon-expand', 'far fa-plus-square'],
        'defaultCollapseNodeIcon' => ['glyphicon glyphicon-collapse', 'far fa-minus-square'],
        'defaultCheckedNodeIcon' => ['glyphicon glyphicon-checked', 'far fa-check-square'],
        'defaultUncheckedNodeIcon' => ['glyphicon glyphicon-unchecked', 'far fa-square'],
        'defaultChildNodeIcon' => ['glyphicon glyphicon-file', 'fas fa-file'],
        'defaultParentNodeIcon' => ['glyphicon glyphicon-folder', 'fas fa-folder', 'kv-node-closed'],
        'defaultParentNodeOpenIcon' => ['glyphicon glyphicon-folder-open', 'fas fa-folder-open', 'kv-node-opened'],
    ];

    /**
     * @var string the icons list show type set under iconEditSettings
     */
    protected $_iconsListShow;

    /**
     * Returns the tree view module
     *
     * @return \yii\base\Module
     * @throws InvalidConfigException
     */
    public static function module()
    {
        return Config::getModule(Module::MODULE);
    }

    /**
     * Initialize icons
     *
     * @throws InvalidConfigException|Exception
     */
    protected function initIcons()
    {
        $notBs3 = !$this->isBs(3);
        foreach (static::$_nodeIcons as $prop => $setting) {
            if (!isset($this->$prop)) {
                $icon = $notBs3 || $this->fontAwesome ? $setting[1] : $setting[0];
                if (isset($setting[2])) {
                    $icon .= ' ' . $setting[2];
                }
                $this->$prop = Html::tag('i', '', ['class' => $icon]);
            }
        }
    }

    /**
     * Generates the configuration for the widget based on
     * module level defaults
     *
     * @param array $config the widget configuration
     *
     * @return array
     * @throws InvalidConfigException
     */
    public static function getConfig($config = [])
    {
        $module = self::module();
        if (!empty($module->treeViewSettings)) {
            $config = array_replace_recursive($module->treeViewSettings, $config);
        }
        return $config;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public static function begin($config = [])
    {
        $config = self::getConfig($config);
        return parent::begin($config);
    }

    /**
     * @inheritdoc
     */
    public static function widget($config = [])
    {
        $config = self::getConfig($config);
        return parent::widget($config);
    }

    /**
     * Check if the trait is used by a specific class or recursively by
     * any of the parent classes or parent traits
     *
     * @param string $class the class name to check
     * @param string $trait the trait class name
     * @param boolean $autoload whether to autoload the class
     *
     * @return boolean whether the class has used the trait
     */
    protected static function usesTrait($class, $trait, $autoload = false)
    {
        $traits = [];
        do {
            $traits = array_merge(class_uses($class, $autoload), $traits);
            if (in_array($trait, $traits)) {
                return true;
            }
        } while ($class = get_parent_class($class));
        $traitsToSearch = $traits;
        while (!empty($traitsToSearch)) {
            $newTraits = class_uses(array_pop($traitsToSearch), $autoload);
            $traits = array_merge($newTraits, $traits);
            if (in_array($trait, $traits)) {
                return true;
            }
            $traitsToSearch = array_merge($newTraits, $traitsToSearch);
        }
        foreach ($traits as $t => $str) {
            $traits = array_merge(class_uses($t, $autoload), $traits);
            if (in_array($trait, $traits)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parses a boolean variable and returns as integer
     *
     * @param boolean $var the variable to parse
     *
     * @return integer
     */
    protected static function parseBool($var)
    {
        return $var ? 1 : 0;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function init()
    {
        $this->initTreeView();
        parent::init();
        $this->initOptions();
        $this->registerAssets();
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function run()
    {
        echo $this->renderWidget();
    }

    /**
     * Initialize all options & settings for the widget
     * @throws InvalidConfigException
     */
    public function initOptions()
    {
        $notBs3 = !$this->isBs(3);
        $this->_iconsListShow = ArrayHelper::getValue($this->iconEditSettings, 'show', 'text');
        if (!$this->_module->treeStructure['treeAttribute']) {
            $this->allowNewRoots = false;
        }
        if (!isset($this->hideCssClass)) {
            $this->hideCssClass = $this->getCssClass(self::BS_HIDE);
        }
        $this->initIcons();
        $this->_nodes = $this->query->all();
        if (!isset($this->iconPrefix)) {
            $this->iconPrefix = !$this->isBs(3) ? 'fas fa-' : ($this->fontAwesome ? 'fa fa-' : 'glyphicon glyphicon-');
        }
        $this->_nodeSelected = $this->options['id'] . '-nodesel';
        $this->initSelectedNode();
        $this->nodeFormOptions['id'] = $this->options['id'] . '-nodeform';
        $this->options['data-key'] = $this->displayValue;
        if (empty($this->buttonIconOptions['class'])) {
            $this->buttonIconOptions['class'] = $this->fontAwesome ? 'kv-icon-10' : 'kv-icon-05';
        }
        if (empty($this->options['class'])) {
            $this->options['class'] = 'form-control ' . $this->hideCssClass ;
        }
        Html::addCssClass($this->treeWrapperOptions, ['kv-tree-wrapper', 'tree-bs' . $this->getBsVer()]);
        Html::addCssClass($this->headerOptions, 'kv-header-container');
        Html::addCssClass($this->headingOptions, 'kv-heading-container');
        Html::addCssClass($this->toolbarOptions, 'kv-toolbar-container');
        Html::addCssClass($this->footerOptions, 'kv-footer-container');
        $css = ['kv-tree-container'];
        if ($this->showCheckbox) {
            $css[] = 'kv-has-checkbox';
        }
        if (!$this->multiple) {
            $css[] = 'kv-single-select';
        }
        Html::addCssClass($this->treeOptions, $css);
        Html::addCssClass($this->rootOptions, 'kv-tree-root');
        Html::addCssClass($this->nodeToggleOptions, 'kv-node-toggle');
        Html::addCssClass($this->nodeCheckboxOptions, 'kv-node-checkbox');
        Html::addCssClass($this->rootNodeToggleOptions, 'kv-root-node-toggle');
        Html::addCssClass($this->rootNodeCheckboxOptions, 'kv-root-node-checkbox');
        Html::addCssClass($this->detailOptions, 'kv-detail-container');
        Html::addCssClass($this->searchContainerOptions, 'kv-search-container');
        Html::addCssClass($this->searchOptions, 'kv-search-input');
        Html::addCssClass($this->searchClearOptions, 'kv-search-clear');
        Html::addCssClass($this->expandNodeOptions, 'kv-node-expand');
        Html::addCssClass($this->collapseNodeOptions, 'kv-node-collapse');
        Html::addCssClass($this->childNodeIconOptions, 'kv-node-icon');
        Html::addCssClass($this->parentNodeIconOptions, 'kv-node-icon');
        Html::addCssClass($this->childNodeIconOptions, 'kv-icon-child');
        Html::addCssClass($this->parentNodeIconOptions, 'kv-icon-parent');
        if (empty($this->searchClearOptions['title'])) {
            $this->searchClearOptions['title'] = Yii::t('kvtree', 'Clear search results');
        }
        Html::addCssClass($this->buttonGroupOptions, 'btn-group');
        $this->treeWrapperOptions['id'] = $this->options['id'] . '-wrapper';
        $this->treeOptions['id'] = $this->options['id'] . '-tree';
        $this->detailOptions['id'] = $this->options['id'] . '-detail';
        $this->toolbarOptions['id'] = $this->options['id'] . '-toolbar';
        if (!isset($this->searchOptions['placeholder'])) {
            $this->searchOptions['placeholder'] = Yii::t('kvtree', 'Search...');
        }
        $this->toolbarOptions['role'] = 'toolbar';
        $this->buttonGroupOptions['role'] = 'group';
        $p = ['node' => $this->nodeTitle, 'nodes' => $this->nodeTitlePlural];
        $this->clientMessages += [
            'invalidCreateNode' => Yii::t('kvtree', 'Cannot create {node}. Parent node is not saved or is invalid.',
                $p),
            'emptyNode' => Yii::t('kvtree', '(new)', $p),
            'removeNode' => Yii::t('kvtree', 'Are you sure you want to remove this {node}?', $p),
            'nodeRemoved' => Yii::t('kvtree', 'The {node} was removed successfully.', $p),
            'nodeRemoveError' => Yii::t('kvtree', 'Error while removing the {node}. Please try again later.', $p),
            'nodeNewMove' => Yii::t('kvtree', 'Cannot move this {node} as the {node} details are not saved yet.', $p),
            'nodeTop' => Yii::t('kvtree', 'Already at top-most {node} in the hierarchy.', $p),
            'nodeBottom' => Yii::t('kvtree', 'Already at bottom-most {node} in the hierarchy.', $p),
            'nodeLeft' => Yii::t('kvtree', 'Already at left-most {node} in the hierarchy.', $p),
            'nodeRight' => Yii::t('kvtree', 'Already at right-most {node} in the hierarchy.', $p),
            'emptyNodeRemoved' => Yii::t('kvtree', 'The untitled {node} was removed.', $p),
            'selectNode' => Yii::t('kvtree', 'Select a {node} by clicking on one of the tree items.', $p),
            'noChildAllowed' => Yii::t('kvtree', 'You cannot add children under this {node}.', $p),
            'nodeTitle' => $this->nodeTitle,
            'nodeTitlePlural' => $this->nodeTitlePlural,
        ];
        $defaultToolbar = [
            self::BTN_CREATE => [
                'icon' => 'plus',
                'alwaysDisabled' => false, // set this property to `true` to force disable the button always
                'options' => ['title' => Yii::t('kvtree', 'Add new'), 'disabled' => true],
            ],
            self::BTN_CREATE_ROOT => [
                'icon' => $this->fontAwesome ? 'tree' : 'tree-conifer',
                'options' => ['title' => Yii::t('kvtree', 'Add new root')],
            ],
            self::BTN_REMOVE => [
                'icon' => 'trash',
                'options' => ['title' => Yii::t('kvtree', 'Delete'), 'disabled' => true],
            ],
            self::BTN_SEPARATOR,
            self::BTN_MOVE_UP => [
                'icon' => 'arrow-up',
                'options' => ['title' => Yii::t('kvtree', 'Move Up'), 'disabled' => true],
            ],
            self::BTN_MOVE_DOWN => [
                'icon' => 'arrow-down',
                'options' => ['title' => Yii::t('kvtree', 'Move Down'), 'disabled' => true],
            ],
            self::BTN_MOVE_LEFT => [
                'icon' => 'arrow-left',
                'options' => ['title' => Yii::t('kvtree', 'Move Left'), 'disabled' => true],
            ],
            self::BTN_MOVE_RIGHT => [
                'icon' => 'arrow-right',
                'options' => ['title' => Yii::t('kvtree', 'Move Right'), 'disabled' => true],
            ],
            self::BTN_SEPARATOR,
            self::BTN_REFRESH => [
                'icon' => $notBs3 ? 'sync-alt' : 'refresh',
                'options' => ['title' => Yii::t('kvtree', 'Refresh')],
                'url' => Yii::$app->request->url,
            ],
        ];
        $this->toolbar = array_replace_recursive($defaultToolbar, $this->toolbar);
        if (!$this->allowNewRoots) {
            unset($this->toolbar[self::BTN_CREATE_ROOT]);
        }
        $this->sortToolbar();
        $this->_nodeIconsList = $this->getIconsList();
    }

    /**
     * Renders the widget markup
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function renderWidget()
    {
        $content = Lib::strtr(
            $this->mainTemplate, [
                '{wrapper}' => $this->renderWrapper(),
                '{detail}' => $this->renderDetail(),
            ]
        );
        return Lib::strtr(
                $content, [
                    '{heading}' => $this->renderHeading(),
                    '{search}' => $this->renderSearch(),
                    '{toolbar}' => $this->renderToolbar(),
                ]
            ) . "\n" .
            Html::textInput('kv-node-selected', $this->value, $this->options) . "\n";
    }

    /**
     * Renders the tree wrapper container
     *
     * @return string
     * @throws Exception
     */
    public function renderWrapper()
    {
        $content = Lib::strtr(
            $this->wrapperTemplate, [
                '{header}' => $this->renderHeader(),
                '{tree}' => $this->renderTree(),
                '{footer}' => $this->renderFooter(),
            ]
        );
        return Html::tag('div', $content, $this->treeWrapperOptions);
    }

    /**
     * Renders the markup for the button actions toolbar
     *
     * @return string
     * @throws Exception
     */
    public function renderToolbar()
    {
        $out = Html::beginTag('div', $this->toolbarOptions) . "\n" .
            Html::beginTag('div', $this->buttonGroupOptions);
        foreach ($this->toolbar as $btn => $settings) {
            if ($settings === false) {
                continue;
            }
            if ($settings === self::BTN_SEPARATOR) {
                $out .= "\n</div>\n" . Html::beginTag('div', $this->buttonGroupOptions);
                continue;
            }
            $icon = ArrayHelper::getValue($settings, 'icon', '');
            $label = ArrayHelper::getValue($settings, 'label', '');
            $iconOptions = ArrayHelper::getValue($settings, 'iconOptions', []);
            $options = ArrayHelper::getValue($settings, 'options', []);
            $iconOptions = array_replace_recursive($this->buttonIconOptions, $iconOptions);
            $options = array_replace_recursive($this->buttonOptions, $options);
            Html::addCssClass($options, 'kv-toolbar-btn kv-' . $btn);
            if (!empty($icon)) {
                $icon = $this->renderIcon($icon, $iconOptions);
                $label = empty($label) ? $icon : $icon . ' ' . $label;
            }
            $alwaysDisabled = ArrayHelper::getValue($settings, 'alwaysDisabled', false);
            if ($alwaysDisabled) {
                $options['disabled'] = true;
                $options['data-always-disabled'] = true;
                if (!empty($settings['url'])) {
                    $options['data-url'] = $settings['url'];
                }
            }
            if (!empty($settings['url']) && !$alwaysDisabled) {
                $out .= "\n" . Html::a($label, $settings['url'], $options);
            } else {
                $out .= "\n" . Html::button($label, $options);
            }
        }
        $out .= "</div>\n</div>";
        return $out;
    }

    /**
     * Renders the root markup for the tree
     *
     * @return string
     */
    public function renderRoot()
    {
        $content = $this->renderToggleIconContainer(true);
        if ($this->showCheckbox) {
            $content .= $this->renderCheckboxIconContainer(true);
        }
        $content .= ArrayHelper::remove($this->rootOptions, 'label', Yii::t('kvtree', 'Root'));
        return Html::tag('div', $content, $this->rootOptions);
    }

    /**
     * Renders the markup for the tree header container
     *
     * @return string
     */
    public function renderHeader()
    {
        return Html::tag('div', $this->headerTemplate, $this->headerOptions);
    }

    /**
     * Renders the markup for the tree footer container
     *
     * @return string
     */
    public function renderFooter()
    {
        return Html::tag('div', $this->footerTemplate, $this->footerOptions);
    }

    /**
     * Renders the markup for the search input
     *
     * @return string
     */
    public function renderSearch()
    {
        $clearLabel = ArrayHelper::remove($this->searchClearOptions, 'label', '&times;');
        $content = Html::tag('span', $clearLabel, $this->searchClearOptions) . "\n" .
            Html::textInput('kv-tree-search', null, $this->searchOptions);
        return Html::tag('div', $content, $this->searchContainerOptions);
    }

    /**
     * Renders the markup for the tree heading
     *
     * @return string
     */
    public function renderHeading()
    {
        if ($this->topRootAsHeading) {
            Html::addCssClass($this->rootOptions, 'kv-root-heading');
            return $this->renderRoot();
        }
        $heading = ArrayHelper::remove($this->headingOptions, 'label', '');
        return Html::tag('div', $heading, $this->headingOptions);
    }

    /**
     * Renders the markup for the tree hierarchy - uses a fast non-recursive mode of tree traversal.
     *
     * @return string
     * @throws Exception
     */
    public function renderTree()
    {
        $structure = $this->_module->treeStructure + $this->_module->dataStructure;
        extract($structure);
        $nodeDepth = $currDepth = $counter = 0;
        $out = Html::beginTag('ul', ['class' => 'kv-tree']) . "\n";
        foreach ($this->_nodes as $node) {
            /**
             * @var Tree $node
             */
            if (!$this->isAdmin && !$node->isVisible() || !$this->showInactive && !$node->isActive()) {
                continue;
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeDepth = $node->$depthAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeLeft = $node->$leftAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeRight = $node->$rightAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeKey = $node->$keyAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeName = $node->$nameAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeIcon = $node->$iconAttribute;
            /** @noinspection PhpUndefinedVariableInspection */
            $nodeIconType = $node->$iconTypeAttribute;

            $isChild = ($nodeRight == $nodeLeft + 1);
            $indicators = '';

            if (isset($this->nodeLabel)) {
                $label = $this->nodeLabel;
                $nodeName = is_callable($label) ? $label($node) :
                    (is_array($label) ? ArrayHelper::getValue($label, $nodeKey, $nodeName) : $nodeName);
            }
            if ($nodeDepth == $currDepth) {
                if ($counter > 0) {
                    $out .= "</li>\n";
                }
            } elseif ($nodeDepth > $currDepth) {
                $out .= Html::beginTag('ul') . "\n";
                $currDepth = $currDepth + ($nodeDepth - $currDepth);
            } elseif ($nodeDepth < $currDepth) {
                $out .= Lib::str_repeat("</li>\n</ul>", $currDepth - $nodeDepth) . "</li>\n";
                $currDepth = $currDepth - ($currDepth - $nodeDepth);
            }
            if (Lib::trim($indicators) == null) {
                $indicators = '&nbsp;';
            }
            $nodeOptions = [
                'data-key' => $nodeKey,
                'data-lft' => $nodeLeft,
                'data-rgt' => $nodeRight,
                'data-lvl' => $nodeDepth,
                'data-disabled' => static::parseBool($node->isDisabled()),
                'data-readonly' => static::parseBool($node->isReadonly()),
                'data-movable-u' => static::parseBool($node->isMovable('u')),
                'data-movable-d' => static::parseBool($node->isMovable('d')),
                'data-movable-l' => static::parseBool($node->isMovable('l')),
                'data-movable-r' => static::parseBool($node->isMovable('r')),
                'data-removable' => static::parseBool($node->isRemovable()),
                'data-removable-all' => static::parseBool($node->isRemovableAll()),
                'data-child-allowed' => static::parseBool($node->isChildAllowed()),
            ];

            $css = [];
            if (!$isChild) {
                $css[] = 'kv-parent ';
            }
            if (!$node->isVisible() && $this->isAdmin) {
                $css[] = 'kv-invisible';
            }
            if ($this->showCheckbox && $node->isSelected()) {
                $css[] = 'kv-selected ';
            }
            if ($node->isCollapsed()) {
                $css[] = 'kv-collapsed ';
            }
            if ($node->isDisabled()) {
                $css[] = 'kv-disabled ';
            }
            if (!$node->isActive()) {
                $css[] = 'kv-inactive ';
            }
            $indicators .= $this->renderToggleIconContainer() . "\n";
            $indicators .= $this->showCheckbox ? $this->renderCheckboxIconContainer() . "\n" : '';
            if (!empty($css)) {
                Html::addCssClass($nodeOptions, $css);
            }
            $out .= Html::beginTag('li', $nodeOptions) . "\n" .
                Html::beginTag('div', ['tabindex' => -1, 'class' => 'kv-tree-list']) . "\n" .
                Html::beginTag('div', ['class' => 'kv-node-indicators']) . "\n" .
                $indicators . "\n" .
                '</div>' . "\n" .
                Html::beginTag('div', ['tabindex' => -1, 'class' => 'kv-node-detail']) . "\n" .
                $this->renderNodeIcon($nodeIcon, $nodeIconType, $isChild) . "\n" .
                Html::tag('span', $nodeName, ['class' => 'kv-node-label']) . "\n" .
                '</div>' . "\n" .
                '</div>' . "\n";
            ++$counter;
        }
        $out .= Lib::str_repeat("</li>\n</ul>", $nodeDepth) . "</li>\n";
        $out .= "</ul>\n";
        if (!$this->hideTopRoot && !$this->topRootAsHeading) {
            $out = $this->renderRoot() . $out;
        }
        return Html::tag('div', $out, $this->treeOptions);
    }

    /**
     * Renders the markup for the detail form to edit/view the selected tree node
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function renderDetail()
    {
        /**
         * @var Tree $modelClass
         * @var Tree $node
         */
        $modelClass = $this->query->modelClass;
        $node = $this->displayValue ? $modelClass::findOne($this->displayValue) : null;
        if (empty($node)) {
            $node = new $modelClass;
        }
        $iconTypeAttribute = ArrayHelper::getValue($this->_module->dataStructure, 'iconTypeAttribute', 'icon_type');
        if ($this->_nodeIconsList !== false) {
            $node->$iconTypeAttribute = ArrayHelper::getValue($this->iconEditSettings, 'type', self::ICON_CSS);
        }
        $url = Yii::$app->request->url;
        $manageData = TreeSecurity::parseManageData([
            'formOptions' => $this->nodeFormOptions,
            'hideCssClass' => $this->hideCssClass,
            'modelClass' => $modelClass,
            'formAction' => $this->nodeActions[Module::NODE_SAVE],
            'currUrl' => $url,
            'isAdmin' => $this->isAdmin,
            'iconsListShow' => $this->_iconsListShow,
            'iconsList' => $this->_nodeIconsList,
            'softDelete' => $this->softDelete,
            'allowNewRoots' => $this->allowNewRoots,
            'showFormButtons' => $this->showFormButtons,
            'showIDAttribute' => $this->showIDAttribute,
            'showNameAttribute' => $this->showNameAttribute,
            'nodeView' => $this->nodeView,
            'nodeAddlViews' => $this->nodeAddlViews,
            'nodeViewButtonLabels' => $this->nodeViewButtonLabels,
            'nodeViewParams' => serialize($this->nodeViewParams),
            'nodeSelected' => $this->_nodeSelected,
            'breadcrumbs' => $this->breadcrumbs,
            'noNodesMessage' => Html::tag('div', $this->emptyNodeMsg, $this->emptyNodeMsgOptions),
            'nodeTitle' => $this->nodeTitle,
            'nodeTitlePlural' => $this->nodeTitlePlural,
            'defaultBtnCss' => $this->getDefaultBtnCss(),
        ]);
        $removeData = TreeSecurity::parseRemoveData([
            'modelClass' => $modelClass,
            'softDelete' => $this->softDelete,
        ]);
        $moveData = TreeSecurity::parseMoveData([
            'modelClass' => $modelClass,
            'allowNewRoots' => $this->allowNewRoots,
        ]);
        $params = $this->_module->treeStructure + $this->_module->dataStructure + [
                'node' => $node,
                'treeManageHash' => $manageData['newHash'],
                'treeRemoveHash' => $removeData['newHash'],
                'treeMoveHash' => $moveData['newHash'],
            ] + $manageData['out'] + $this->nodeViewParams;
        $content = $this->render($this->nodeView, ['params' => $params]);
        return Html::tag('div', $content, $this->detailOptions);
    }

    /**
     * Registers the client assets for the widget
     * @throws Exception
     */
    public function registerAssets()
    {
        $view = $this->getView();
        TreeViewAsset::register($view);
        if ($this->_hasBootstrap && $this->autoLoadBsPlugin) {
            PluginAssetBundle::registerBundle($view, $this->bsVersion);
        }
        Dialog::widget($this->krajeeDialogSettings);
        $this->pluginOptions += [
            'dialogLib' => ArrayHelper::getValue($this->krajeeDialogSettings, 'libName', 'krajeeDialog'),
            'hideCssClass' => $this->hideCssClass,
            'treeId' => $this->treeOptions['id'],
            'detailId' => $this->detailOptions['id'],
            'toolbarId' => $this->toolbarOptions['id'],
            'wrapperId' => $this->treeWrapperOptions['id'],
            'actions' => $this->nodeActions,
            'modelClass' => $this->query->modelClass,
            'formAction' => $this->nodeActions[Module::NODE_SAVE],
            'formOptions' => $this->nodeFormOptions,
            'currUrl' => Yii::$app->request->url,
            'messages' => $this->clientMessages,
            'alertFadeDuration' => $this->alertFadeDuration,
            'enableCache' => ArrayHelper::getValue($this->cacheSettings, 'enableCache', true),
            'cacheTimeout' => ArrayHelper::getValue($this->cacheSettings, 'cacheTimeout', 300000),
            'showTooltips' => $this->showTooltips,
            'isAdmin' => $this->isAdmin,
            'showInactive' => $this->showInactive,
            'softDelete' => $this->softDelete,
            'iconsListShow' => $this->_iconsListShow,
            'iconsList' => $this->_nodeIconsList,
            'showFormButtons' => $this->showFormButtons,
            'showIDAttribute' => $this->showIDAttribute,
            'showNameAttribute' => $this->showNameAttribute,
            'nodeView' => $this->nodeView,
            'nodeAddlViews' => $this->nodeAddlViews,
            'nodeViewParams' => serialize($this->nodeViewParams),
            'nodeViewButtonLabels' => $this->nodeViewButtonLabels,
            'nodeSelected' => $this->_nodeSelected,
            'defaultBtnCss' => $this->getDefaultBtnCss(),
            'noNodesMessage' => Html::tag('div', $this->emptyNodeMsg, $this->emptyNodeMsgOptions),
            'breadcrumbs' => $this->breadcrumbs,
            'multiple' => $this->multiple,
            'cascadeSelectChildren' => $this->cascadeSelectChildren,
            'allowNewRoots' => $this->allowNewRoots,
            'hideUnmatchedSearchItems' => $this->hideUnmatchedSearchItems
        ];
        $this->pluginOptions['rootKey'] = self::ROOT_KEY;
        $this->registerPlugin('treeview');
    }

    /**
     * Initializes and validates the tree view configurations
     *
     * @throws InvalidConfigException
     */
    protected function initTreeView()
    {
        if (empty($this->nodeTitle)) {
            $this->nodeTitle = Yii::t('kvtree', 'node');
        }
        if (empty($this->nodeTitlePlural)) {
            $this->nodeTitlePlural = Yii::t('kvtree', 'nodes');
        }
        $this->validateSourceData();
        $this->_module = Config::initModule(Module::class);
        if (!isset($this->bsVersion) && isset($this->_module->bsVersion)) {
            $this->bsVersion = $this->_module->bsVersion;
        }
        $this->initBsVersion();
        $notBs3 = !$this->isBs(3);
        $prefix = $this->getDefaultIconPrefix();
        $defaultBtnCss = $this->getDefaultBtnCss();
        if (empty($this->emptyNodeMsg)) {
            $this->emptyNodeMsg = Yii::t(
                'kvtree',
                'No valid {nodes} are available for display. Use toolbar buttons to add {nodes}.',
                ['node' => $this->nodeTitle, 'nodes' => $this->nodeTitlePlural]
            );
        }

        if (!isset($this->nodeViewButtonLabels['submit'])) {
            $this->nodeViewButtonLabels['submit'] = Html::tag('i', '',
                ['class' => $prefix . ($notBs3 ? 'save' : 'floppy-disk')]);
        }
        if (!isset($this->nodeViewButtonLabels['reset'])) {
            $this->nodeViewButtonLabels['reset'] = Html::tag('i', '',
                ['class' => $prefix . ($notBs3 ? 'redo' : 'repeat')]);
        }
        if (!isset($this->buttonOptions['class'])) {
            $this->buttonOptions['class'] = 'btn ' . $defaultBtnCss;
        }
        $this->_hasBootstrap = $this->showTooltips;
        $this->breadcrumbs += [
            'depth' => '',
            'glue' => ' &raquo; ',
            'activeCss' => 'kv-crumb-active',
            'untitled' => Yii::t('kvtree', 'Untitled'),
        ];
    }

    /**
     * Initializes the selected node
     *
     * @return void
     */
    protected function initSelectedNode()
    {
        if (!Yii::$app->has('session')) {
            return;
        }
        $session = Yii::$app->session;
        $id = isset($this->_nodeSelected) ? $this->_nodeSelected : 'kvNodeId';
        $key = $session->get($id);
        if ($key) {
            $this->displayValue = $key;
        }
        $session->set($id, null);
    }

    /**
     * Validation of source query data
     *
     * @throws InvalidConfigException
     */
    protected function validateSourceData()
    {
        if (empty($this->query) || !$this->query instanceof ActiveQuery) {
            throw new InvalidConfigException(
                "The 'query' property must be defined and must be an instance of '" . ActiveQuery::class . "'."
            );
        }
        $class = isset($this->query->modelClass) ? $this->query->modelClass : null;
        if (empty($class) || !is_subclass_of($class, ActiveRecord::class)) {
            throw new InvalidConfigException("The 'query' must be implemented using 'ActiveRecord::find()' method.");
        }
        $trait = 'igorkri\tree\models\TreeTrait';
        if (!self::usesTrait($class, $trait)) {
            throw new InvalidConfigException(
                "The model class '{$class}' for the 'query' must use the trait '{$trait}' or extend from '" .
                Tree::class . "''."
            );
        }
    }

    /**
     * Sorts the toolbar based on `toolbarOrder` configuration
     */
    protected function sortToolbar()
    {
        if (empty($this->toolbarOrder)) {
            return;
        }
        $sortedToolbar = [];
        foreach ($this->toolbarOrder as $btnKey) {
            if ($btnKey === self::BTN_SEPARATOR) {
                $sortedToolbar[] = $btnKey;
            } elseif (isset($this->toolbar[$btnKey])) {
                $sortedToolbar[$btnKey] = $this->toolbar[$btnKey];
            }
        }
        $this->toolbar = $sortedToolbar;
    }

    /**
     * Render the default node icon markup.
     *
     * @param string $icon the current node's icon
     * @param integer $iconType the current node's icon type, must be one of:
     * - `TreeView::ICON_CSS` or `1`: if the icon css class suffix name is stored in $icon.
     * - `TreeView::ICON_RAW` or `2`: if the raw icon markup is stored in $icon.
     * @param boolean $child whether child or parent
     *
     * @return string
     */
    protected function renderNodeIcon($icon, $iconType, $child = true)
    {
        if (!empty($icon)) {
            $options = $child ? $this->childNodeIconOptions : $this->parentNodeIconOptions;
            $css = $this->iconPrefix . $icon;
            $icon = $iconType == self::ICON_CSS ? Html::tag('span', '', ['class' => $css]) : $icon;
            return Html::tag('span', $icon, $options);
        }
        $content = $this->defaultParentNodeIcon . $this->defaultParentNodeOpenIcon;
        return Html::tag('span', $content, $this->parentNodeIconOptions) .
            Html::tag('span', $this->defaultChildNodeIcon, $this->childNodeIconOptions);
    }

    /**
     * Renders the default toggle icon markup based on fontAwesome setting
     *
     * @param string $action whether 'collapse' or 'expand'
     *
     * @return string
     */
    protected function renderToggleIcon($action = 'collapse')
    {
        $icon = $action == 'expand' ? $this->defaultExpandNodeIcon : $this->defaultCollapseNodeIcon;
        $options = $action == 'expand' ? $this->expandNodeOptions : $this->collapseNodeOptions;
        $label = ArrayHelper::remove($options, 'label', $icon);
        return Html::tag('span', $label, ['class' => "kv-node-{$action}"]);
    }

    /**
     * Renders the toggle icon container
     *
     * @param boolean $root whether a root node
     *
     * @return string
     */
    protected function renderToggleIconContainer($root = false)
    {
        $content = $this->renderToggleIcon('expand') . $this->renderToggleIcon();
        $options = $root ? $this->rootNodeToggleOptions : $this->nodeToggleOptions;
        return Html::tag('span', $content, $options);
    }

    /**
     * Renders the checkbox icon markup based on fontAwesome setting
     *
     * @param boolean $checked whether 'checked'
     *
     * @return string
     */
    protected function renderCheckboxIcon($checked = false)
    {
        $icon = $checked ? $this->defaultCheckedNodeIcon : $this->defaultUncheckedNodeIcon;
        $options = $checked ? $this->checkedNodeOptions : $this->uncheckedNodeOptions;
        $label = ArrayHelper::remove($options, 'label', $icon);
        $action = $checked ? 'checked' : 'unchecked';
        return Html::tag('span', $label, ['class' => "kv-node-{$action}"]);
    }

    /**
     * Renders the checkbox icon container
     *
     * @param boolean $root whether its a root node
     *
     * @return string
     */
    protected function renderCheckboxIconContainer($root = false)
    {
        $content = $this->renderCheckboxIcon(true) . $this->renderCheckboxIcon();
        $options = $root ? $this->rootNodeCheckboxOptions : $this->nodeCheckboxOptions;
        return Html::tag('span', $content, $options);
    }

    /**
     * Renders a generic icon using icon suffix
     *
     * @param string $icon the icon suffix name
     * @param array $options the HTML attributes for the icon container
     *
     * @return string
     */
    protected function renderIcon($icon, $options = [])
    {
        Html::addCssClass($options, $this->iconPrefix . $icon);
        return Html::tag('span', '', $options);
    }

    /**
     * Renders the markup for the detail form to edit/view the selected tree node
     *
     * @return null|array
     * @throws Exception
     */
    protected function getIconsList()
    {
        if ($this->_iconsListShow != 'list') {
            return null;
        }
        $type = ArrayHelper::getValue($this->iconEditSettings, 'type', self::ICON_CSS);
        $settings = ArrayHelper::getValue($this->iconEditSettings, 'listData', []);
        if ($type === self::ICON_RAW) {
            return $settings;
        }
        $newSettings = [
            '' => '<em>' . Yii::t('kvtree', 'Default') . '</em> ( ' .
                Html::tag('span', $this->defaultParentNodeIcon, $this->parentNodeIconOptions) . ' / ' .
                Html::tag('span', $this->defaultParentNodeOpenIcon, $this->parentNodeIconOptions) . ' / ' .
                Html::tag('span', $this->defaultChildNodeIcon, $this->childNodeIconOptions) . ')',
        ];
        foreach ($settings as $suffix => $label) {
            $newSettings[$suffix] = Html::tag('span', '', ['class' => $this->iconPrefix . $suffix]) . ' ' . $label;
        }
        return $newSettings;
    }
}
