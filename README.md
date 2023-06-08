```
$ composer require igorkri/yii2-tree-manager "~1.0.0"
```

or add

```
"igorkri/yii2-tree-manager": "~1.0.0"
```

to the ```require``` section of your `composer.json` file.

## Usage

### Step 1: Prepare Database

Create your database table to store the tree structure. You can do it in one of the following ways:

#### Option 1: Run DB Migrations

You can run the migrations script provided to create the database structure from your yii programming console:

```
php yii migrate/up --migrationPath=@vendor/igorkri/yii2-tree-manager/src/migrations
```

#### Option 2: Executing SQL script

Alternatively, you can execute the SQL script to generate your DB structure. Copy and modify the `migrations/tree.sql` file (a MySQL example), to create the table `tbl_tree` (or for any table name you need). 

>NOTE: You can add columns you need to this table, but you cannot skip/drop any of the columns mentioned in the script. You can choose to rename the `id`, `root`, `lft`, `rgt`, `lvl`, `name`, `icon`, `icon_type` columns if you choose to - but these must be accordingly setup in the module.



Alternatively, you can configure your model to not extend from `igorkri\tree\models\Tree` and instead implement and use the `igorkri\tree\models\TreeTrait`:

```php
namespace frontend\models;

use Yii;

class Tree extends \yii\db\ActiveRecord
{
    use igorkri\tree\models\TreeTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tbl_tree';
    }    
}
```

### Step 3: Setup Module

Configure the module named `treemanager` in the modules section of your Yii configuration file.

```php
'modules' => [
   'treemanager' =>  [
        'class' => '\igorkri\tree\Module',
        // other module settings, refer detailed documentation
    ]
]
```

### Step 4: Using TreeView Widget

In your view files, you can now use the tree view directly to manage tree data as shown below:

```php
use igorkri\tree\TreeView;
echo TreeView::widget([
    // single query fetch to render the tree
    'query'             => Tree::find()->addOrderBy('root, lft'), 
    'headingOptions'    => ['label' => 'Categories'],
    'isAdmin'           => false,                       // optional (toggle to enable admin mode)
    'displayValue'      => 1,                           // initial display value
    //'softDelete'      => true,                        // normally not needed to change
    //'cacheSettings'   => ['enableCache' => true]      // normally not needed to change
]);
```

### Step 5: Using TreeViewInput Widget

If you wish to use the tree input to select tree items, you can use the TreeViewInput widget as shown below. Normally you would use this as a dropdown with the `asDropdown` property set to `true`. If `asDropdown` is set to `false`, the treeview input widget will be rendered inline for selection.

```php
use igorkri\tree\TreeViewInput;
echo TreeViewInput::widget([
    // single query fetch to render the tree
    'query'             => Tree::find()->addOrderBy('root, lft'), 
    'headingOptions'    => ['label' => 'Categories'],
    'name'              => 'kv-product',    // input name
    'value'             => '1,2,3',         // values selected (comma separated for multiple select)
    'asDropdown'        => true,            // will render the tree input widget as a dropdown.
    'multiple'          => true,            // set to false if you do not need multiple selection
    'fontAwesome'       => true,            // render font awesome icons
    'rootOptions'       => [
        'label' => '<i class="fa fa-tree"></i>', 
        'class'=>'text-success'
    ],                                      // custom root label
    //'options'         => ['disabled' => true],
]);
```