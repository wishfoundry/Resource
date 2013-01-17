Resource
========

A resourceful route creator


example:
```php
Resource::route('post')
				->with('comments')
				->with('likes');
```

will create the following routes:

```
GET    posts
GET    posts/
POST   posts
POST   posts/
GET    posts/create
GET    posts/create/
GET    posts/{id}
GET    posts/{id}/
GET    posts/{id}/edit
PUT    posts/{id}
PATCH  posts/{id}
DELETE posts/{id}
GET    posts/{id}/delete


and will map them to:
```
PostController@{method}
CommentController@{method}
LikeController@{method}
```

you can pass an array of options as well:

```php
Resource::route('ox' [
	'uses'      => '\My\Custom\ControllerClass',
	'before'    => 'auth|admin',  // set before filters
	'after'     => 'tokenify',    // set after  filters
	'resources' => 'oxen',        // the resource plural name (automatically inflected if not provided)
	'mode'      => 'strict',      // enabled|disable trailing slashes and redirect codes
	'adds'      => 'flag|unflag'  // add extra routes to methods with the same name. Currently only GET routes are supported, but "post:flag|delete:flag" format may be added in future
    'format'    => 'xml|json|'    // add dot separated extended routes(e.g /posts/234.xml and /posts/234.json )
    //'regex'   => '\d+'          // set validator ( not yet implemented )
    'embed'     => true|false     // if set to true, will match to methods on the parent controller instead of a separate controller
]);
```

Resourceful methods are:
```
@index
@show
@store
@edit
@create
@update
@delete
```

sub-resource methods are(e.g. comment)
```
@commentsIndex
@showComment
@storeComment
@editComment
@createComment
@updateComment
@deleteComment
```
no mass  assigment routes are created

'Resource' will also set the named routes for the resource. For example "posts" would be set as:
```
posts
post
new_post
edit_post
if any options arr added to the "adds" field (e.g. flag):
flag_post
custom_post
etc
```



