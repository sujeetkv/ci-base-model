CodeIgniter Base Model
=====================================

By- Sujeet <sujeetkv90@gmail.com>

CodeIgniter Base Model is an extended CI_Model class to use in your CodeIgniter applications. It provides a bunch of CRUD operation methods to perform database interaction easier in our application, with provision of intelligent table name guessing and assigning primary key of model automatically. It makes database interactions code DRY and also provide inter-model relation feature giving a little bit of ORM flavour.

Synopsis
--------

```php
class Post_model extends MY_Model
{
    // model class
}


$this->load->model('post_model', 'post');

$this->post->find(1);
$this->post->findOneById(1);// magic finder

$this->post->findAll();
$this->post->findAll('title, content');

$this->post->findBy(array( 'title' => 'The Demo Post!' ));
$this->post->findByTitle('The Demo Post!');// magic finder

$this->post->findOneBy('status', 1);
$this->post->findOneByStatus(1);// magic finder

$this->post->findValue('title', array( 'id' => 10 ));

$this->post->order('created', 'desc')->limit(20)->findAll();

$this->post->countAll(array( 'status' => 1 ));

$this->post->create(array(
    'status' => 1,
    'title' => "The Post of Demo!",
    'content' => "The demo content."
));

$this->post->updateBy(array( 'id' => 1 ), array( 'status' => 0 ));
$this->post->updateById(1, array( 'status' => 0 ));

$this->post->deleteBy(array( 'id' => 1 ));
$this->post->deleteById(1);
```

Installation / Usage
--------------------

Download and put the MY\_Model.php file into _application/core_ folder. CodeIgniter will load and initialise this class automatically.

Extend your model classes from `MY_Model` and all the functionality will be available in your model automatically.

Naming Conventions
------------------

The model will try to guess the name of the table to use, by finding the plural of the model name. 

For example:

    class Post_model extends MY_Model { }

...will guess a table name as `posts`. It also works with suffix `_m`:

    class Book_m extends MY_Model { }

...will guess `books`.

If you need to set table name to something else, you can declare the _$table_ instance variable and set it to the table name:

    class Post_model extends MY_Model
    {
        protected $table = 'blogposts';
    }

Some of the CRUD and relation functions use primary key ID column that is assigned automatically by model. You can overwrite this by setting the _$primary\_key_ instance variable:

    class Post_model extends MY_Model
    {
        protected $primary_key = 'post_id';
    }


Model Relations
---------------

_MY\_Model_ has support for basic `belongs_to` and `has_many` relationships. These relationships are easy to define:

```php
class Post_model extends MY_Model
{
    protected $belongs_to = array( 'user' );
    protected $has_many = array( 'comments' );
}
```

It will assume that a MY_Model API-compatible model with the singular relationship's name has been defined. By default, this will be `<singular of relationship>_model`. The above example, for instance, would require two other models:

    class User_model extends MY_Model { }
    class Comment_model extends MY_Model { }

If you'd like to customise this, you can pass through the model name as a parameter:

```php
class Post_model extends MY_Model
{
    protected $belongs_to = array( 'user' => array( 'model' => 'user_m' ) );
    protected $has_many = array( 'comments' => array( 'model' => 'model_comments' ) );
}
```

You can then access your related data using the `with()` method:

```php
$post = $this->post_model->with('user')
                         ->with('comments')
                         ->find(1);
```

The related data will be embedded in the returned value from `getById`:

```php
echo $post->user->name;

foreach ($post->comments as $comment)
{
    echo $comment->content;
}
```

You can access chained related data recursively using the `withRecursive()` method:

```php
$post = $this->post_model->withRecursive()->find(1);
```

You can pass recursion level to `withRecursive()` method.

Separate queries will be run to select the data, so where performance is important, a separate JOIN and SELECT call is recommended.

The relation key can also be configured. For _belongs\_to_ calls, the related key is on the current object, not the foreign one. Pseudocode:

    SELECT * FROM users WHERE id = $post->user_id

...and for a _has\_many_ call:

    SELECT * FROM comments WHERE post_id = $post->id

To change this, use the `foreign_key` value when configuring relation:

```php
class Post_model extends MY_Model
{
    public $belongs_to = array( 'users' => array( 'foreign_key' => 'post_user_id' ) );
    public $has_many = array( 'comments' => array( 'foreign_key' => 'parent_post_id' ) );
}
```

Complete relation format is:

```php
class Post_model extends MY_Model
{
    public $has_many = array(
                           'comments' => array(
                               'model' => 'model_comments',
                               'foreign_key' => 'parent_post_id',
                               'fields' => 'id, content',
                               'limit' => array(5, 0),
                               'order' => array('id', 'desc'),
                               'scope' => array('status' => 1)
                           )
                       );
}
```

Arrays / Objects
-----------------

By default, MY_Model is setup to return objects using CodeIgniter's QueryBuilder's `row()` and `result()` methods. If you'd like to use their array counterparts, there are a couple of ways of customising the model.

If you'd like all your calls to use the array methods, you can set the `$default_return_type` variable to `array`.

```php
class Book_model extends MY_Model
{
    protected $default_return_type = 'array';
}
```

If you'd like just your _next_ call to return a specific type, there are two scoping methods you can use:

```php
$this->book_model->asArray()->find(1);
$this->book_model->asObject()->find(1);
```

Special Save Feature
--------------------

You can save data to model's table by using `save()` method. This method will insert or update data based on whether you have passed `$primary_key` field in data or not.

Following will update the data of record with `id = 1`:

```php
$this->post->save(array(
    'id' => 1,
    'title' => "The Post of Demo!",
    'content' => "The demo content."
));
```

Following will insert new record with data:

```php
$this->post->save(array(
    'title' => "The Post of Demo!",
    'content' => "The demo content."
));
```

If table has any of columns named `created` and `modified` with type *datetime* or *timestamp*, these will automatically be populated with current time value if not provided with data. If you don't want to update `modified` column's value, just set its value as `false` in data.

```php
$this->post->save(array(
    'id' => 1,
    'title' => "The Post of Demo!",
    'content' => "The demo content.",
    'modified' => false
));
```
We can specify observer/callback method for `save()` named as *brforeSave()* in our model. It will be called before any call to `save()` method. Data to be saved will be passed to `beforeSave()` and must be return by this method. You can perform any operation (e.g. Validation or Modification) on data in this method. If you don't want to proceed to respective `save()` method (e.g. in case of invalid data), just retun false from `beforeSave()` method otherwise return passed data as it is or modified.

```php
public function beforeSave($data, $table){
    // do any stuff with $data
    return $data;
}
```

Notes:
------

Many methods of `MY_Model` accept parameter named `$options`. This can be used to call any valid CodeIgniter DB Object's method except `get*` methods:

```php
$posts = $this->post->findBy(array('status' => 1), "posts.*, authors.name", NULL, array(
    'join' => array('authors', 'posts.author_id = authors.id', 'left'),
    'order_by' => array('posts.id', 'desc')
));
```

*Many methods of `MY_Model` accept parameter to pass table name, so that we can use them for any table of database.*
