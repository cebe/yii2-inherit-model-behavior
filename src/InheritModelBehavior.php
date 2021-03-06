<?php

namespace sergin\yii2\behaviors;


use InvalidArgumentException;
use ReflectionClass;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\web\Request;

/**
 * Class InheritModelBehavior
 * @package sergin\yii2\behaviors
 * @property \yii\db\ActiveRecord $inheritModel
 * @property \yii\db\ActiveRecord $owner
 */
class InheritModelBehavior extends Behavior
{
    public $dependClass;

    /**
     * @var array|callable Depend class configuration that uses on object init;
     * or closure that call after object init (created object will set as first closure argument)
     */
    public $dependClassInitConfig = [];

    /** @var string option name that will be use */
    public $virtualOption;

    /** @var ActiveRecord option in class that contains already saved related objects. By default, ActiveRecord getter */
    public $relationMethod;

    /** @var string primary key name at inherit model */
    public $primaryKeyName = 'id';

    /** @var string option name in owner model that contain primary key value of inherit model */
    public $linkAttribute;

    /** @var  boolean Is depend class object should be initialize when no one items present */
    public $createDependObjectOnEmpty = true;

    /** @var boolean is need run inherit model's `delete()` when owner deleting */
    public $deleteWithOwner = true;

    /** @var  ActiveRecord */
    protected $_inheritModel;

    /** @var bool is need to parse simple options like 'bar' instead of 'Foo[bar]'at request */
    public $simpleRequest = false;

    /** @inheritDoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if (empty($this->dependClass) && empty($this->virtualOption)) {
            throw new InvalidConfigException('$dependClass and $virtualOption should be published for DynamicClassBehavior');
        }
        if (empty($this->relationMethod)) {
            $this->relationMethod = 'get' . ucfirst($this->virtualOption);
        }
        if (empty($this->linkAttribute)) {
            $this->linkAttribute = $this->virtualOption . '_id';
        }
    }

    /** @inheritDoc */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'load',
            ActiveRecord::EVENT_AFTER_VALIDATE => 'validate',
            ActiveRecord::EVENT_BEFORE_INSERT => 'save',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'save',
            ActiveRecord::EVENT_BEFORE_DELETE => 'delete',
        ];
    }

    /**
     * @return bool is inherit model load successfully (or true if no model exist)
     * @throws \ReflectionException
     */
    public function load()
    {
        $data = $this->parseIncomingData(Yii::$app->request);
        return !empty($data) && $this->inheritModel ? $this->inheritModel->load($data, '') : true;
    }

    /**
     * @param $request \yii\web\Request
     *
     * @return array parsed options values for inherit model. Example of simple request:
     *  [
     *      'id' => 213,
     *      'title' => 'Lorem ipsum dolor",
     *      'description' => 'Lorem impsum dolor sit amet',
     *  ]
     * @throws \ReflectionException
     */
    protected function parseIncomingData($request)
    {
        if (!(Yii::$app->request instanceof Request)) {
            return null; // skip if not web
        }

        if ($this->simpleRequest) {
            $postData = $request->post();
            return empty($postData) ? $request->get() : $postData;
        }

        $class = (new ReflectionClass($this->dependClass))->getShortName();
        $postData = $request->post($class);
        return empty($postData) ? $request->get($class) : $postData;
    }

    public function validate()
    {
        $isValid = true;
        if ($this->inheritModel && !$isValid = $this->inheritModel->validate()) {
            $this->owner->addErrors($this->inheritModel->errors);
        }
        return $isValid;
    }

    public function save()
    {
        $isSaved = true;
        if ($this->inheritModel && $isSaved = $this->inheritModel->save()) {
            $this->owner->{$this->linkAttribute} = $this->inheritModel->{$this->primaryKeyName};
        }
        return $isSaved;
    }

    /**
     * @return false|int|null count of deleted records, false if nothing to delete,
     * null if no inherit model exist or deletion disabled
     * @throws \Throwable see ActiveRecord::delete()
     * @throws \yii\db\StaleObjectException see ActiveRecord::delete()
     */
    public function delete()
    {
        if (!$this->deleteWithOwner) return null;
        return $this->inheritModel ? $this->inheritModel->delete() : null;
    }

    /** @inheritDoc */
    public function __get($name)
    {
        switch ($name) {
            case $this->virtualOption:
                return $this->inheritModel;
            default:
                return parent::__get($name);
        }
    }

    /** @inheritDoc */
    public function canGetProperty($name, $checkVars = true)
    {
        return parent::canGetProperty($name, $checkVars) || $name === $this->virtualOption;
    }

    /** @inheritDoc */
    public function canSetProperty($name, $checkVars = true)
    {
        return parent::canSetProperty($name, $checkVars) || $name === $this->virtualOption;
    }

    /** @inheritDoc */
    public function __set($name, $value)
    {
        switch ($name) {
            case $this->virtualOption:
                if (is_null($value) || $value instanceof $this->dependClass) {
                    return $this->_inheritModel = $value;
                } else {
                    throw new InvalidArgumentException($this->virtualOption . ' should be null or instance of ' . $this->dependClass);
                }
            default:
                return parent::__get($name);
        }
    }

    public function initNewInheritModel()
    {
        if (is_array($this->dependClassInitConfig)) {
            return new $this->dependClass($this->dependClassInitConfig);
        } elseif (is_callable($this->dependClassInitConfig)) {
            $dependObject = new $this->dependClass;
            call_user_func($this->dependClassInitConfig, $dependObject);
        } else {
            $dependObject = new $this->dependClass;
        }
        return $dependObject;
    }

    protected function getInheritModel()
    {
        if (empty($this->_inheritModel)) {
            $this->_inheritModel = $this->owner->{$this->relationMethod}()->findFor($this->virtualOption, $this->owner);

            if (empty($this->_inheritModel) && $this->createDependObjectOnEmpty) {
                $this->_inheritModel = $this->initNewInheritModel();
                if (method_exists($this->owner, 'populateRelation')) {
                    $this->owner->populateRelation($this->virtualOption, $this->_inheritModel);
                }
            }
        }
        return $this->_inheritModel;
    }
}
