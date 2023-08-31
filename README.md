[![Latest Stable Version](http://poser.pugx.org/idetik/coretik/v)](https://packagist.org/packages/idetik/coretik) [![License](http://poser.pugx.org/idetik/coretik/license)](https://github.com/idetik/coretik/blob/master/LICENSE.md)
# Coretik : Wordpress framework

Manage models, queries, services and more...

## Installation

`composer require idetik/coretik`


## Get started

### Schema : declare custom post type and taxonomies

#### Simple use case

```php
use Coretik\Core\Builders\Taxonomy;
use Coretik\Core\Builders\PostType;

use App\Model\MyModel;
use App\Query\MyQuery;

// Declare post type
PostType::make('my_custom_post_type')
    ->setSingularName('Title')
    ->setPluralName('Titles')
    ->addToSchema();

// Declare taxonomy
Taxonomy::make('my_taxonomy')
    ->setSingularName('Title')
    ->setPluralName('Titles')
    ->for('my_custom_post_type')
    ->addToSchema();
```

#### Advanced

This is an advanced use case using [models](#models), [queries](#queries), [handlers](#handlers) and [macros](#macros)

##### Post type

```php
use Coretik\Core\Builders\Taxonomy;

use App\Model\MyModel;
use App\Query\MyQuery;
use App\Handler\MyHandlerA;
use App\Handler\MyHandlerB;

// Accept same arguments as register_post_type() (https://developer.wordpress.org/reference/functions/register_post_type/) and register_extended_post_type() (https://github.com/johnbillion/extended-cpts/wiki/Registering-Post-Types)
$register_extended_post_type_args = [];

PostType::make('my_custom_post_type')
    ->setSingularName('Title')
    ->setPluralName('Titles')
    ->setArgs($register_extended_post_type_args) // Optional, 
    ->factory(fn ($initializer) => new MyModel($initializer)) // Optional, you can add a custom model factory or use the default factory built in Coretik 
    ->querier(fn ($builder) => new MyQuery($builder)) // Optional, you can add a custom query class or use the default querier built in Coretik
    ->handler(MyHandlerA::class) // Optional, you can use many handlers on the same builder
    ->handler(MyHandlerB::class)
    ->attach('myMacroA', 'my_callable') // Optional, you can attach all callables you want
    ->attach('myMacroB', 'my_callable')
    ->addToSchema();
```

##### Taxonomy

```php
use Coretik\Core\Builders\Taxonomy;

use App\Model\MyTermModel;
use App\Query\MyTermQuery;
use App\Handler\MyTermHandlerA;
use App\Handler\MyTermHandlerB;

// Accept same arguments as register_taxonomy() (https://developer.wordpress.org/reference/functions/register_taxonomy/) and register_extended_taxonomy() (https://github.com/johnbillion/extended-cpts/wiki/Registering-taxonomies)
$register_extended_taxonomy_args = [];

Taxonomy::make('my_custom_taxonomy')
    ->setSingularName('Title')
    ->setPluralName('Titles')
    ->setArgs($register_extended_taxonomy_args) // Optional, 
    ->factory(fn ($initializer) => new MyTermModel($initializer)) // Optional, you can add a custom model factory or use the default factory built in Coretik 
    ->querier(fn ($builder) => new MyTermQuery($builder)) // Optional, you can add a custom query class or use the default querier built in Coretik
    ->handler(MyTermHandlerA::class) // Optional, you can use many handlers on the same builder
    ->handler(MyTermHandlerB::class)
    ->attach('myMacroA', 'my_callable') // Optional, you can attach all callables you want
    ->attach('myMacroB', 'my_callable')
    ->addToSchema();
```

### Models

As model in MVC design pattern, it contains only the pure application data. This makes it easier to maintain and scale the application over time
It supports relathionships between post types and taxonomies or others customs stuffs.

### Set a custom model class for object
#### Setup

```php
use Coretik\Core\Models\Wp\PostModel;

class MyPostModel extends PostModel
{
    public function foo()
    {
        return 'bar';
    }
}

$postSchema = app()->schema('post');
$postSchema->factory(fn ($initializer) => new MyPostModel($initializer));
```

#### Usage

```php
$models = app()->schema('post')->query()->models();

foreach ($models as $model) {
    echo $model->foo(); // 'bar'
}
```

#### Advanced

Models can handle post metas easily, including protected metas. Models provides a meta accessor like object properties. Metas has to be declared in the model constructor.
Only declared metas will be saved in database on a CRUD action.

```php
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Collection;

class MyPostModel extends PostModel
{
    public function __construct($initializer = null)
    {
        parent::__construct($initializer);

        $this->declareMetas([
            'ma_meta_a' => 'bdd_field_name',
            'ma_meta_b' => 'other_bdd_field_name',
        ]);

        // Each meta
        // @see Core/Models/MetaDefinition.php
        $this->metaDefinition('ma_meta_a')->castTo('array');
        $this->metaDefinition('ma_meta_b')->protectWith(fn ($model) => (bool)$model->canIUpdateThisValue());
    }

    public function canIUpdateThisValue(): bool
    {
        // @todo create a guard
        return true;
    }

    public function foo(): string
    {
        if (in_array('bar', $this->get('ma_meta_a'))) {
            return 'bar';
        }

        return 'foo';
    }

    /**
     * Accessor
     * 
     * To define an accessor, create a getFooAttribute method on your model where Foo is the "studly" cased name of the column you wish to access.
     * In this example, we'll define an accessor for the first_name attribute. The accessor will automatically be called when attempting to retrieve the value of the first_name attribute:
     */
    public function getFirstNameAttribute(): string
    {
        if (empty($this->get('ma_meta_b'))) {
            return 'toto';
        }
        
        return $this->get('ma_meta_b');
    }

    /**
     * Mutator
     * 
     * To define a mutator, define a setFooAttribute method on your model where Foo is the "studly" cased name of the column you wish to access.
     * So, again, let's define a mutator for the first_name attribute. This mutator will be automatically called when we attempt to set the value of the first_name attribute on the model:
     */
    public function setFirstNameAttribute($value)
    {
        $this->ma_meta_b = strtolower($value);
    }

    /**
     * Relationships
     * 
     * For now, only post <-> taxonomy relationships are ready to use with wp-admin. Posts to posts relationships (1, n) require an extra handler on the post type builder, who update the post_parent column on save post. We are working to include this feature on a next release.
     */
    public function setCategory(ModelInterface|int|string $category): self
    {
        $this->detachTerm($category, 'my_category_taxonomy');
        $this->setTerm($city, 'my_category_taxonomy');
        return $this;
    }

    public function category(): ?ModelInterface
    {
        return $this->hasOne('my_category_taxonomy');
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes()->each(fn ($attribute) => $this->detachTerm($attribute, 'my_attributes_taxonomy'));
        $this->attributes = [];
        foreach ($attributes as $attr) {
            $this->setTerm($attribute, 'my_attributes_taxonomy');
        }
        return $this;
    }

    public function attributes(): Collection
    {
        return $this->hasMany('my_attributes_taxonomy');
    }

    public function addToGroup(int $post_group_id): self
    {
        $this->post_parent = $group_id;
        return $this;
    }

    public function group(): ?ModelInterface
    {
        return $this->belongsTo('my_post_type_group'); // require an extra handler
    }
}

$postSchema = app()->schema('my_custom_post_type');
$postSchema->factory(fn ($initializer) => new MyPostModel($initializer));
```

Create & save a model :
```php
$model = app()->schema('my_custom_post_type')->model();
$model->post_title = 'Mr Bar Foo';
$model->post_status = 'publish';
$model->ma_meta_a = 'Foo';
$model->first_name = 'Bar';
$model->setAttributes(['smart', 'tall']);
$model->addToGroup(100); // a post with title 'Groupe 1' from 'my_post_type_group' with ID 100, 
$model->save();

$modelId = $model->id();
```

Use a model :

```php
$myModel = app()->schema('my_custom_post_type')->model($modelId);

echo $myModel->foo(); // foo
echo $myModel->ma_meta_a; // ['Foo']
echo $myModel->first_name; // bar
echo $myModel->ma_meta_b; // bar
echo $myModel->group()->title(); // Groupe 1
$myModel->attributes()->each(fn ($attributeModel) => echo $attributeModel->title() . ', '); // smart, tall, 
```


### Queries

Use coretik queries to retrieve models behind complex clauses. Queries are more readables and useables than a basic wp_query who need to manage metas, taxonomies and settings directly.
Four kinds of query are ready to use: PostQuery, TermQuery, UserQuery and CommentQuery.

#### Simple query

One way to query all wp_post filtered by default query args, and browse result models :

See `src/Core/Query/Post::getQueryArgsDefault()`

```php
$models = app()->schema('my_custom_post_type')->query()->models();

foreach ($models as $model) {
    echo $model->title();
}
```
#### Others query
See `src/Core/Query/Adapters` folder. 


### Set a custom query class for object
#### Setup

```php
use Coretik\Core\Query\Post as PostQuery;
use Coretik\Core\Models\Interfaces\ModelInterface;

class MyPostQuery enxtends PostQuery
{
    public function myCustomFilter(): self
    {
        $this->set([...]);
        $this->whereMeta([...]);
        $this->whereTax([...]);
        return $this;
    }

    public function ordered(): self
    {
        $this->set('orderby', 'menu_order title');
        $this->set('order', 'ASC');
        return $this;
    }

    public function category(ModelInterface|string|int $category): self
    {
        if (is_string($category)) {
            $this->whereTax('my_taxonomy', $category, 'IN', 'slug');
        } else {
            if ($category instanceof ModelInterface) {
                $category = $category->id();
            }
            $this->whereTax('my_taxonomy', $category);
        }
        return $this;
    }

    public function inGroup(int $group_id): self
    {
        $this->set('post_parent', $group_id);
        return $this;
    }

    public function withAttribute(ModelInterface|string|int $attribute): self
    {
        if ($attribute instanceof ModelInterface) {
            $this->whereTax('my_attributes_taxonomy', $attribute->id());
        } elseif (\is_int($attribute)) {
            $this->whereTax('my_attributes_taxonomy', $attribute);
        } else {
            $this->whereTax('my_attributes_taxonomy', $attribute, 'IN', 'slug');
        }
        return $this;
    }
}

$postSchema = app()->schema('my_custom_post_type');
$postSchema->querier(fn ($builder) => new MyPostQuery($builder));
```

#### Usage

```php
$result = app()
            ->schema('my_custom_post_type')
                ->query()
                    ->ordered()
                    ->inGroup(100)
                    ->withAttribute('smart')
                        ->or('small')
                    ->whereMeta('ma_meta_b', 'bar')
                    ->first();

echo $result->title(); // Mr Bar Foo
```

### Dependency Injection Container

A coretik application uses Pimple as Dependency injection containers.
As many other dependency injection containers, Pimple manages two different kind of data: services and parameters.
Please read the official documentation on Github: https://github.com/silexphp/Pimple.
Coretik comes with severals services built in. You can find them in `src/Services` (doc in the todoux list...)

First, create your application container in your functions.php :

```php
use Coretik\App;
use Coretik\Core\Container;

$container = new Container();

$container['my-service'] = function ($container) {
    return new MyService();
};

App::run($container);
```
