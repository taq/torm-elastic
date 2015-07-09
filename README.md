# TORM-elasticsearch

This is a trait to insert ElasticSearch funcionality on
[TORM](https://github.com/taq/torm) objects. 

## Installation

Change (or create, if needed) your Composer file to include it:

```
{
    "require": {
        "taq/torm-elastic": ">=0"
    }
}
```

## Usage

Just open your model and insert the trait, like:

```
class User extends User {
    use TORM\ElasticSearch;
}
User::setElasticSearchIndex("myapp");
```

and, after every object saving, it will be send for ElasticSearch indexing,
using some rules:

- Need to insert the trait using `use TORM\ElasticSearch` on the model;
- Need to specify the app name using setElasticSearchIndex(<name>). This will be
  the ElasticSearch index.
- After inserting the trait, a new `afterInitialize` method will be added on the
  model. If the model already has a `afterInitialize` method, **the
  `TORM\ElasticSearch` `afterInitialize` method must be called explicity on its
  end**. This is because of the way PHP traits works.
- If not specified, **all the model attributes** will be indexed. To define just
  some key attributes, we can use the `setElasticSearchValues(<attributes>)`
  method, sending an array with the attributes, like:
  ```
  User::setElasticSearchValues(["name"]);
  ```
  then only the `name` attribute will be indexed.

## Searching

Then we can search using something like:

```
$rtn = ElasticUser::elasticSearch("name", "john");
var_dump($rtn);
```

resulting in something like

```
array(2) {
  'id' =>
  string(1) "1"
  'name' =>
  string(12) "John Doe Jr."
}
```

## Importing 

When importing a new data collection, we can use the `import` method, like:

```
User::elasticImport();
```

## Updating a document

We can explicity update a document using:

```
$obj->updateElasticSearch();
```

## Deleting a document

We can explicity delete a document using:

```
$obj->deleteElasticSearch();
```
