#Ardent

Self-validating smart models for Laravel Framework 4's Eloquent O/RM.

Based on the Aware bundle for Laravel 3 by Colby Rabideau.

##Installation


Add `laravelbook/ardent` as a requirement to `composer.json`:

```javascript
{
    "require": {
        "laravelbook/ardent": "1.0.*"
    }
}
```

Update your packages with `composer update` or install with `composer install`.

## Documentation

* [Getting Started](#start)
* [Effortless Validation with Ardent](#validation)
* [Retrieving Validation Errors](#errors)
* [Overriding Validation](#override)
* [`beforeSave` and `afterSave` Hooks](#beforesave)
* [Custom Validation Error Messages](#messages)
* [Custom Validation Rules](#rules)

<a name="start"></a>
## Getting Started

Ardent aims to extend the Eloquent base class without changing its core functionality. All Eloquent models are fully compatible with Ardent.

To create a new Ardent model, simply make your model class descend from the `Ardent` base class:

```php
class User extends Ardent {}
```

<a name="validation"></a>
## Effortless Validation with Ardent

Ardent models use Laravel's built-in [Validator class](http://doc.laravelbook.com/validation/). Defining validation rules for a model is simple:

```php
class User extends Ardent {

  /**
   * Ardent validation rules
   */
  public static $rules = array(
    'name' => 'required|between:4,16',
    'email' => 'required|email',
	'password' => 'required|alpha_num|between:4,8|confirmed',
	'password_confirmation' => 'required|alpha_num|between:4,8',
  );

  ...

}
```

Ardent models validate themselves automatically when `Ardent->save()` is called.

```php
$user = new User();
$user->name = 'John doe';
$user->email = 'john@doe.com';
$user->password = 'test';
$user->save(); // returns false if model is invalid
```

**note:** You also can validate a model at any time using the `Ardent->validate()` method.

<a name="errors"></a>
## Retrieving Validation Errors

When an Ardent model fails to validate, a `Illuminate\Support\MessageBag` object is attached to the Ardent object.

Retrieve the validation errors message collection instance with `Ardent->errors()` method or `Ardent->validationErrors` property.

Retrieve all validation errors with `Ardent->errors()->all()`. Retrieve errors for a *specific* attribute using `Ardent->validationErrors->get('attribute')`.

> **Note:** Ardent leverages Laravel's MessagesBag object which has a [simple and elegant method](http://doc.laravelbook.com/validation/#working-with-error-messages) of formatting errors.

<a name="overide"></a>
## Overriding Validation

There are two ways to override Ardent's validation:

#### 1. Forced Save
`forceSave()` validates the model but saves regardless of whether or not there are validation errors.

#### 2. Override Rules and Messages
both `Ardent->save($rules, $customMessages)` and `Ardent->validate($rules, $customMessages)` take two parameters:

- `$rules` is an array of Validator rules of the same form as `Ardent::rules`.
- The same is true of the `$customMessages` parameter (same as `Ardent::customMessages`)

An array that is **not empty** will override the rules or custom error messages specified by the class for that instance of the method only.

> **Note:** the default value for `$rules` and `$customMessages` is `array()`, if you pass an `array()` nothing will be overriden

<a name="beforesave"></a>
## beforeSave and afterSave Hooks

Ardent provides a convenient method for performing actions when `$model->save()` is called. For example, use `beforeSave` to automatically hash a users password:

```php
class User extends Ardent {

  public function beforeSave()
  {
    // if there's a new password, hash it
    if($this->changed('password'))
    {
      $this->password = Hash::make($this->password);
    }

    return true;
  }

}
```

Notice that `beforeSave` returns a boolean. If you would like to halt the `save()` operation, simply return false.

> **Note:** `forceSave()` has it's own `beforeForceSave()` and `afterForceSave()` hooks.

### Overriding beforeSave and afterSave

Just like, `$rules` and `$customMessages`, `beforeSave` and `afterSave` can be overridden at run-time. Simply pass the closures to the `save()` (or `forceSave()`) function.

```php
$user-save(array(), array(), 
	function ($model) {
	  echo "saving!";
	  return true;
	},
	function ($model) {
	  echo "saved!";
	}
);
```
> **Note:** the closures should have one parameter as it will be passed a reference to the model being saved.

<a name="messages"></a>
## Custom Error Messages

Just like the Laravel Validator, Ardent lets you set custom error messages using the [same sytax](http://doc.laravelbook.com/validation/#custom-error-messages).

```php
class User extends Ardent {

  /**
   * Ardent Messages
   */
  public static $customMessages = array(
    'required' => 'The :attribute field is required.'
  );

  ...

}
```

<a name="rules"></a>
## Custom Validation Rules

You can create custom validation rules the [same way](http://doc.laravelbook.com/validation/#custom-validation-rules) you would for the Laravel Validator.
