# Laravel Eloquent Database Filler

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brahmic/laravel-filler.svg?style=flat-square)](https://packagist.org/packages/brahmic/laravel-filler)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/brahmic/laravel-filler/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/brahmic/laravel-filler/actions?query=workflow%3Arun-tests+branch%3Amaster)


## Concept

When working with data via the API, we often receive these entities with nested relationships, but when sending data,
the nested relationships have to be processed manually. This hydration pack allows you not to think about it. And this
greatly speeds up development.

## Peculiarities

- Allows you to work with input and output of Eloquent models “as is” - without unnecessary manipulations
- Unit of work - either *all* changes will be made, or (in case of an error) changes will not be made at all
- Idenity map - guarantees that entities of the same type and with the same identifier - are essentially the same
- uuid - allows you to create valid entities and link them together by identifier, without accessing the database

## Content

- [Idea](#Idea)
- [Features](#features)
- [Restrictions](#restrictions)
- [Installation](#installation)
- [Usage](#usage)
  - [Backend example](#backend-example)
  - [Frontend example](#frontend-example)
- [Input features](#input-features)
  - [Flat entities](#flat-entities)
  - [HasOne](#hasone)
  - [HasMany](#hasmany)
  - [BelongsTo](#belongsto)
  - [BelongsToMany](#belongstomany)
  - [MorphTo](#morphto)
  - [MorphOne](#morphone)
  - [MorphMany](#morphmany)
  - [MorphToMany](#morphtomany)
- [Output features](#output-features)
- [Testing](#testing)

## Restrictions

Currently only works with uuid

## Installation

```
composer require brahmic/laravel-filler
```

## Usage

This thing is very easy to use

[👆](#content)

### Backend example

```php
<?php

namespace App\Http\Controllers;

use App\Post;
use Exception;
use Brahmic\Filler\Filler;
use Illuminate\Http\Request;

class PostController
{
 /**
 * @param $id
 * @param Request $request
 * @param Filler $filler
 * @return Post
 * @throws Exception
 */
 public function put(Request $request, Filler $filler): Post
 {
     $post = Post::findOrNew($request->get('id'));
     $filler->filler($post, $request->all());
    
     // here we can do something before the changes are sent to the database.
    
     $filler->flush();
    
     return $post;
 }
}
```

[👆](#content)

### Frontend example

(don't do this - this is just an example)

```js
import uuid from 'uuid/v4'

class Post {
    constructor(data) {
        if (!data.id) {
            data.id = uuid()
        }
        Object.assign(this, data)
    }

    addTag(tag) {
        this.tags.push(tag)
    }

    addImage(image) {
        this.images.push(image)
    }
}

class Tag {
    constructor(data) {
        if (!data.id) {
            data.id = uuid()
        }
        Object.assign(this, data)
    }
}

let post, tags;

//
function loadTags() {
    fetch('tags')
        .then(response => response.json())
        .then(tagsData => tags = data.map(tagdata => new Tag(tagdata)))

}

function loadPost(id) {
    fetch(`posts/${id}`)
        .then(response => response.json())
        .then(data => post = new Post(data))
}

function savePost(post) {
    fetch(`posts/${post.id}`, {method: 'PUT', body: JSON.stringify(post)})
        .then(response => response.json())
        .then(data => alert(`Post ${data.title} saved!`))
}

loadTags()
loadPost(1)

// After everything is loaded:

post.addTag(tags[0])
post.title = 'Hello World!'

savePost(post)

```

[👆](#content)

## Input features

### Flat entities

**Let's take a simple example:**

```json
{
  "name": "Brahmic",
  "email": "mail@example.com"
}
```

Since the passed data does not contain the `id` field (or another field that was specified in the `$primaryKey` model),
the hydrator will create a new entity. And fill it with the transferred data using the standard `fill` method.
In this case, an `id` will be immediately generated for the model.

**Example with ID:**

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "Brahmic",
  "email": "mail@example.com"
}
```

In this example, `id` was passed - so the hydrator will try to find such an entity in the database. However, if it fails
to find such a record in the database, it will create a new entity with the passed `id` .
In any case, the hydrator will fill this model with the passed `email` and `name`. In this case, the behavior is
similar to `User::findORNew($id)`.

[👆](#content)

### HasOne

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "Brahmic",
  "email": "mail@example.com",
  "account": {
    "active": true
  }
}
```

In this case, the hydrator will deal with the first-level entity (user) in the same way as in the example with the
identifier. Then, it will try to find the account - if it does not find it (and in the current example the account does
not have an `id`), it will create a new one. If it finds one with a different identifier, it will replace it with the
newly created one. The old account will be deleted. Of course, in any post field (for example `user_id` or `author_id` -
depending on how it is specified in relation to `User::account()`), the user ID will be written.

[👆](#content)

### HasMany

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "Brahmic",
  "email": "mail@example.com",
  "posts": [
    {
      "id": "1286d5bb-c566-4f3e-abe0-4a5d56095f01",
      "title": "foo",
      "text": "bar"
    },
    {
      "id": "d91c9e65-3ce3-4bea-a478-ee33c24a4628",
      "title": "baz",
      "text": "quux"
    },
    {
      "title": "baz",
      "text": "quux"
    }
  ]
}
```

In the many-to-one example, a hydrator would come with each post entry, as in the `HasOne` example. In addition, all
posts that were not present in the passed array of posts will be deleted.

[👆](#content)

### BelongsTo

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "Brahmic",
  "email": "mail@example.com",
  "organization": {
    "id": "1286d5bb-c566-4f3e-abe0-4a5d56095f01",
    "name": "Acme"
  }
}
```

Although this example looks like `HasOne`, it works differently. If such an organization is found by the hydrator in the
database, the user will be linked to it through the relationship field. On the other hand, if there is no such record,
the user will receive `null` in this field. All other fields of the associated record (organization) will be ignored -
since `User` is not the `aggregate root` of `Organization`, therefore it is not possible to manipulate organization
fields through the user object, nor is it possible to create new organizations.

[👆](#content)

### BelongsToMany

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "Brahmic",
  "email": "mail@example.com",
  "roles": [
    {
      "id": "dcb41b0c-8bc1-490c-b714-71a935be5e2c",
      "pivot": {
        "sort": 0
      }
    }
  ]
}
```

This example is like a mixture of `HasMany` (in the sense that all non-represented records will be removed from the
pivot) and `BlongsTo` (all fields except the `$primaryKey` field will be ignored, for the reasons explained above in
the `belongsTo` section) . Please note that working with a pivot is also available.

### MorphTo

Supported, but not yet described. The principle of operation is similar to that described above.

### MorphOne

Supported, but not yet described. The principle of operation is similar to that described above.

### MorphMany

Supported, but not yet described. The principle of operation is similar to that described above.

---
> Everything described works recursively, and is valid for any degree of nesting.

---
[👆](#content)

## Output Features

It's also worth noting that all passed relationships will be added to the entity during output.
For example:

```php
 $user = $filler->filler(User::class, [
 'id' => '123e4567-e89b-12d3-a456-426655440000',
 'name' => 'Brahmic',
 'email' => 'mail@example.com',
 'roles' => [
         [
             'id' => 'dcb41b0c-8bc1-490c-b714-71a935be5e2c',
             'pivot' => ['sort' => 0],
         ],
     ],
 ]);

 $user->relationLoaded('roles'); // true
 // although the flush has not been done yet, all relationships have already been registered, and there is no need to load them additionally.
 // Calling $user->roles will not cause a repeated request to the database.

 $filler->flush();
 // Only after this the entity with all its connections will be included in the database.
```

[👆](#content)
## Testing

Пакет имеет полный набор тестов для проверки его функциональности. Для запуска тестов можно использовать удобный скрипт:

```bash
# Запуск всех тестов
./run-tests.sh all

# Запуск только модульных тестов
./run-tests.sh unit

# Запуск только функциональных тестов
./run-tests.sh feature
```

Подробная информация о тестировании доступна в [README-TESTING.md](README-TESTING.md).

[👆](#content)

## TODO

- add the ability to persist an entity that has not passed through the hydrator

[👆](#content)



