# yii2-inherit-model-behavior
Use this behavior to connect inherit ActiveRecord with (one-to-many relation)

In parent ActiveRecord should be column that uses to store ID of inherit ActiveRecord.

# Installation


The preferred way to install this extension is through Composer.

Either run php composer.phar require mubat/yii2-inherit-model-behavior "~1.0"

or add "mubat/yii2-inherit-model-behavior": "~1.0" to the require section of your composer.json


## Usage examples
* Simple usage:
```php
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['image'] = [
            'class' => InheritModelBehavior::class,
            'dependClass' => Image::class,
            'virtualOption' => 'image',
            'linkAttribute' => 'image_id',
            'relationMethod' => 'savedImage',
        ];
        return $behaviors;
    }
    
    /** @return \yii\db\ActiveQuery */
    public function getSavedImage()
    {
        return $this->hasOne(Image::class, ['id' => 'image_id']);
    }

```