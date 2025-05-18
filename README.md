# Laravel Eloquent Database Filler

[🇷🇺 Документация на русском языке](readme_ru.md)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brahmic/laravel-filler.svg?style=flat-square)](https://packagist.org/packages/brahmic/laravel-filler)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/brahmic/laravel-filler/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/brahmic/laravel-filler/actions?query=workflow%3Arun-tests+branch%3Amaster)

## Contents

- [Introduction](#introduction)
- [Package Features](#package-features)
- [Limitations](#limitations)
- [Installation](#installation)
- [Usage](#usage)
  - [Backend Example](#backend-example)
  - [Frontend Example](#frontend-example)
- [Supported Relationships](#supported-relationships)
  - [Flat Entities](#flat-entities)
  - [HasOne](#hasone)
  - [HasMany](#hasmany)
  - [BelongsTo](#belongsto)
  - [BelongsToMany](#belongstomany)
  - [MorphTo](#morphto)
  - [MorphOne](#morphone)
  - [MorphMany](#morphmany)
  - [MorphToMany](#morphtomany)
  - [MorphedByMany](#morphedbymany)
  - [HasOneThrough](#hasonethrough)
  - [HasManyThrough](#hasmanythrough)
- [Output Features](#output-features)
- [Best Practices](#best-practices)
- [Testing](#testing)

## Introduction

Laravel Eloquent Database Filler is a package for Laravel that solves the problem of automatically hydrating Eloquent models along with nested relationships based on data received from API requests.

### Problem Statement

When working with data via API, we often receive and send entities with nested relationships. Laravel provides convenient mechanisms for working with relationships when receiving data, but when sending and updating data with nested relationships, these nested structures have to be processed manually. This requires writing a lot of code for:

1. Creating new records in the database
2. Updating existing records
3. Deleting records that are no longer needed
4. Maintaining the integrity of relationships between models

This package automates the entire process of saving complex nested structures to the database, allowing you to work with API data "as is", without the need for manual processing.

## Package Features

- **Work with models "as is"** - no additional data transformations required before saving to the database
- **Unit of Work** - all changes are applied atomically; either all changes will be made, or (in case of an error) no changes will be made at all
- **Identity Map** - guarantees that entities of the same type and with the same identifier are essentially the same object
- **UUID** - allows you to create valid entities and link them together by identifier without accessing the database
- **Recursive processing** - supports arbitrary level of relationship nesting
- **Automatic relationship loading** - all passed relationships are automatically added to the entity as loaded relationships

## Limitations

In the current version, the package only works with models that use UUID as the primary key.

## Installation

```bash
composer require brahmic/laravel-filler
```

After installation, the package will be automatically registered in Laravel through the package auto-discovery mechanism.

## Usage

The package is very easy to use. Main steps:

1. Inject the `Filler` service through dependency injection
2. Use the `fill` method to populate the model with data
3. Call the `flush` method to save all changes to the database

### Backend Example

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
     * @param Request $request
     * @param Filler $filler
     * @return Post
     * @throws Exception
     */
    public function put(Request $request, Filler $filler): Post
    {
        $post = Post::findOrNew($request->get('id'));
        $filler->fill($post, $request->all());
        
        // Here you can do something before the changes are sent to the database
        
        $filler->flush();
        
        return $post;
    }
}
```

### Frontend Example

Sample code for the client side (this is just an example, not recommended to use directly):

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

function loadTags() {
    fetch('tags')
        .then(response => response.json())
        .then(tagsData => tags = tagsData.map(tagdata => new Tag(tagdata)))
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

## Supported Relationships

The package supports all standard Laravel Eloquent relationships:

### Flat Entities

**Simple example without ID:**

```json
{
  "name": "John Smith",
  "email": "mail@example.com"
}
```

Since the passed data does not contain the `id` field (or another field that was specified in the `$primaryKey` model), the hydrator will create a new entity. And fill it with the transferred data using the standard `fill` method. In this case, an `id` will be immediately generated for the model.

**Example with ID:**

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "John Smith",
  "email": "mail@example.com"
}
```

In this example, `id` was passed - so the hydrator will try to find such an entity in the database. However, if it fails to find such a record in the database, it will create a new entity with the passed `id`.
In any case, the hydrator will fill this model with the passed `email` and `name`. In this case, the behavior is similar to `User::findORNew($id)`.

### HasOne

"One-to-one" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "John Smith",
  "email": "mail@example.com",
  "profile": {
    "phone": "+1 (123) 456-7890",
    "address": "123 Main St, New York, NY 10001"
  }
}
```

In this case, the hydrator will deal with the first-level entity (user) in the same way as in the example with the identifier. Then, it will try to find the user's profile - if it does not find it (and in the current example the profile does not have an `id`), it will create a new one. If it finds a profile with a different identifier, it will replace it with the newly created one. The old profile will be deleted.

### HasMany

"One-to-many" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "John Smith",
  "email": "mail@example.com",
  "posts": [
    {
      "id": "1286d5bb-c566-4f3e-abe0-4a5d56095f01",
      "title": "First Post",
      "text": "First post content"
    },
    {
      "id": "d91c9e65-3ce3-4bea-a478-ee33c24a4628",
      "title": "Second Post",
      "text": "Second post content"
    },
    {
      "title": "New Post",
      "text": "New post content"
    }
  ]
}
```

In this example, the hydrator will process each entry in the `posts` array, similar to the example with `HasOne`. In addition, all user posts that were not specified in the passed array will be deleted.

### BelongsTo

"Belongs to" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "title": "New Article",
  "content": "Article content...",
  "author": {
    "id": "1286d5bb-c566-4f3e-abe0-4a5d56095f01",
    "name": "John Smith"
  }
}
```

Although this example looks like `HasOne`, it works differently. If such an author is found by the hydrator in the database, the article will be linked to it through the relationship field. On the other hand, if there is no such record, the article will receive `null` in this field. All other fields of the associated record (author) will be ignored - since `Post` is not the `aggregate root` of `Author`, therefore it is not possible to manipulate author fields through the article object or create new authors.

### BelongsToMany

"Many-to-many" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "title": "New Article",
  "content": "Article content...",
  "tags": [
    {
      "id": "dcb41b0c-8bc1-490c-b714-71a935be5e2c",
      "pivot": {
        "sort": 0
      }
    },
    {
      "id": "fd5ab8de-c467-4969-9c2e-1a3f93a7bd56",
      "pivot": {
        "sort": 1
      }
    }
  ]
}
```

This example is like a mixture of `HasMany` (in the sense that all non-represented records will be removed from the pivot) and `BelongsTo` (all fields except the `$primaryKey` field will be ignored). Please note that working with a pivot table data is also available.

### MorphTo

Polymorphic "belongs to" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "text": "This is a comment on a post or an image...",
  "commentable": {
    "type": "App\\Post",
    "id": "f85ae98c-09d7-4c13-87a4-a3a4b9b96c50"
  }
}
```

In this example, we link a comment to a specific entity through a polymorphic relationship. The `type` field indicates the model class to which the comment should be linked. The principle of operation is similar to the usual `BelongsTo`.

### MorphOne

Polymorphic "one-to-one" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "title": "Post Title",
  "content": "Post content...",
  "image": {
    "path": "/storage/images/profile.jpg",
    "description": "Main illustration"
  }
}
```

In this example, we add an image to a post through a polymorphic relationship. The principle of operation is similar to the usual `HasOne`.

### MorphMany

Polymorphic "one-to-many" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "title": "Post Title",
  "content": "Post content...",
  "comments": [
    {
      "id": "bcf8e5c7-2d6b-4a8f-a7c3-8a71b68e3937",
      "text": "First comment"
    },
    {
      "text": "New comment"
    }
  ]
}
```

In this example, we link a post with several comments through a polymorphic relationship. The principle of operation is similar to the usual `HasMany`.

### MorphToMany

Polymorphic "many-to-many" relationship (from one side):

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "title": "Post Title",
  "content": "Post content...",
  "categories": [
    {
      "id": "dcb41b0c-8bc1-490c-b714-71a935be5e2c",
      "pivot": {
        "featured": true
      }
    },
    {
      "id": "fd5ab8de-c467-4969-9c2e-1a3f93a7bd56"
    }
  ]
}
```

In this example, we link a post with several categories through a polymorphic "many-to-many" relationship. The principle of operation is similar to the usual `BelongsToMany`.

### MorphedByMany

Polymorphic "many-to-many" relationship (from the inverse side):

```json
{
  "id": "dcb41b0c-8bc1-490c-b714-71a935be5e2c",
  "name": "Technology",
  "posts": [
    {
      "id": "123e4567-e89b-12d3-a456-426655440000",
      "pivot": {
        "featured": true
      }
    },
    {
      "id": "f85ae98c-09d7-4c13-87a4-a3a4b9b96c50"
    }
  ]
}
```

This example shows the inverse side of a polymorphic "many-to-many" relationship. The category is linked to posts through a pivot table. The principle of operation is similar to `MorphToMany`.

### HasOneThrough

"One-to-one through" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "United States",
  "cities": [
    {
      "id": "bcf8e5c7-2d6b-4a8f-a7c3-8a71b68e3937",
      "name": "New York",
      "shops": [
        {
          "name": "Central Store",
          "address": "123 Broadway St"
        }
      ]
    }
  ],
  "firstShop": {
    "name": "Central Store",
    "address": "123 Broadway St"
  }
}
```

In this example, the `firstShop` relationship is `HasOneThrough`. Through the intermediate `City` model, we get a connection to the country's first shop. The package correctly handles such relationships.

### HasManyThrough

"One-to-many through" relationship:

```json
{
  "id": "123e4567-e89b-12d3-a456-426655440000",
  "name": "United States",
  "cities": [
    {
      "id": "bcf8e5c7-2d6b-4a8f-a7c3-8a71b68e3937",
      "name": "New York",
      "shops": [
        {
          "name": "Shop 1",
          "address": "123 Broadway St"
        },
        {
          "name": "Shop 2",
          "address": "456 5th Ave"
        }
      ]
    }
  ],
  "shops": [
    {
      "name": "Shop 1",
      "address": "123 Broadway St"
    },
    {
      "name": "Shop 2",
      "address": "456 5th Ave"
    }
  ]
}
```

In this example, the `shops` relationship is `HasManyThrough`. Through the intermediate `City` model, we get a connection to all shops in the country. The package correctly handles such relationships.

> It's important to note that **everything described works recursively** and is valid for any degree of nesting.

## Output Features

It's also worth noting that all passed relationships will be added to the entity during output.
For example:

```php
$user = $filler->fill(User::class, [
    'id' => '123e4567-e89b-12d3-a456-426655440000',
    'name' => 'John Smith',
    'email' => 'mail@example.com',
    'roles' => [
        [
            'id' => 'dcb41b0c-8bc1-490c-b714-71a935be5e2c',
            'pivot' => ['sort' => 0],
        ],
    ],
]);

$user->relationLoaded('roles'); // true
// Although flush has not been done yet, all relationships have already been registered
// and there is no need to load them additionally.
// Calling $user->roles will not cause a repeated request to the database.

$filler->flush();
// Only after this the entity with all its connections will be included in the database.
```

## Best Practices

### API Request Structure

For effective use of the package, it is recommended to adhere to the following structure when building an API:

1. **Flat endpoints for lists** - when retrieving lists of entities, use a flat structure without nested relationships to improve performance

2. **Detailed endpoints with relationships** - when retrieving a single entity, include the necessary relationships

3. **Observe idempotence** - use PUT requests to update data, passing the complete state of the entity

4. **UUID for new entities** - create UUIDs on the client side for new entities to simplify linking nested structures

### Performance Optimization

1. **Limit nesting depth** - too deep nesting can lead to performance issues

2. **Avoid cyclic dependencies** - design your API structure to avoid cyclic dependencies between entities

3. **Use caching** - for frequently requested data, use caching at the application level

### Security Best Practices

1. **Data validation** - always validate incoming data before passing it to Filler

2. **Access control** - implement access checks before saving entities to the database

3. **Transactions** - the flush() method already uses transactions, but if necessary, you can wrap it in an additional transaction

## Testing

The package has a full suite of tests to verify its functionality. To run the tests, you can use a convenient script:

```bash
# Run all tests
./run-tests.sh all

# Run only unit tests
./run-tests.sh unit

# Run only feature tests
./run-tests.sh feature
```

Detailed information about testing is available in [README-TESTING.md](README-TESTING.md).
