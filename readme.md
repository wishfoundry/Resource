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
```

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
@AllCommentsIndex  // subresource index as root
@showComment
@storeComment
@editComment
@createComment
@updateComment
@deleteComment
```
with the following routes:
```
GET    comments
GET    posts/{id}/comments
GET    posts/{id}/comments/
POST   posts/{id}/comments
POST   posts/{id}/comments/
GET    posts/create
GET    posts/create/
GET    posts/{id}
GET    posts/{id}/
GET    posts/{id}/edit
PUT    posts/{id}
PATCH  posts/{id}
DELETE posts/{id}
GET    posts/{id}/delete
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
for subresources:
all_resources  // index as root
resources      // index per each main resource
```


updates:

trailing slash support has been removed. If you need such emulation I recommend you add the following catch-all route to the end of your routes.php

```php
Route::get('{any}', function($url){
    return Redirect::to(mb_substr($url, 0, -1), 301);
})->where('any', '(.*)\/$');
```



