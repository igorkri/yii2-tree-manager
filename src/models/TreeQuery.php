<?php

/**
 * @copyright Copyright &copy; Kartik Visweswaran, Krajee.com, 2015 - 2022
 * @package   yii2-tree-manager
 * @version   1.1.3
 */

namespace igorkri\tree\models;

use yii\behaviors\SluggableBehavior;
use yii\db\ActiveQuery;
use creocoder\nestedsets\NestedSetsQueryBehavior;

/**
 * TreeQuery is the base query class for the nested set tree and used by the [[Tree]] model.
 */
class TreeQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            NestedSetsQueryBehavior::class,
        ];
    }

}
