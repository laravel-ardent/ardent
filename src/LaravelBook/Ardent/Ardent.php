<?php namespace LaravelBook\Ardent;

use Closure;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

/**
 * Ardent - Self-validating Eloquent model base class
 *
 */
abstract class Ardent extends \Illuminate\Database\Eloquent\Model
{

    /**
     * The rules to be applied to the data.
     *
     * @var array
     */
    public static $rules = array();

    /**
     * The array of custom error messages.
     *
     * @var array
     */
    public static $customMessages = array();

    /**
     * The message bag instance containing validation error messages
     *
     * @var Illuminate\Support\MessageBag
     */
    public $validationErrors;

    /**
     * Create a new Ardent model instance.
     *
     * @param array   $attributes
     * @return void
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->validationErrors = new MessageBag;
    }

    /**
     * Validate the model instance
     *
     * @param array   $rules          Validation rules
     * @param array   $customMessages Custom error messages
     * @return bool
     */
    public function validate($rules = array(), $customMessages = array())
    {

        $success = true;

        $data = $this->attributes; // the data under validation
        if (empty($data)) {
            // just extract the fields that are defined in the validation rule-set
            $data = array_intersect_key(Input::all(), $rules);
        }

        if (!empty($data) && (!empty($rules) || !empty(static::$rules))) {

            // check for overrides
            $rules = (empty($rules)) ? static::$rules : $rules;
            $customMessages = (empty($customMessages)) ? static::$customMessages : $customMessages;

            // construct the validator
            $validator = Validator::make($data, $rules, $customMessages);
            $success = $validator->passes();

            if ($success) {
                // if the model is valid, unset old errors
                if ($this->validationErrors->count() > 0) {
                    $this->validationErrors = new MessageBag;
                }
            } else {
                // otherwise set the new ones
                $this->validationErrors = $validator->messages();

                // stash the input to the current session
                Input::flash();
            }

        }

        return $success;
    }

    /**
     * onSave - Invoked before a model is saved. Return false to abort the operation.
     *
     * @return bool
     */
    protected function onSave()
    {
        return true;
    }

    /**
     * onForceSave - Invoked before a model is saved forcefully. Return false to abort the operation.
     *
     * @return bool
     */
    protected function onForceSave()
    {
        return true;
    }

    /**
     * Save the model to the database.
     *
     * @param array   $rules:array
     * @param array   $customMessages
     * @param closure $onSave
     * @return bool
     */
    public function save($rules = array(), $customMessages = array(), Closure $onSave = null)
    {

        // validate
        $validated = $this->validate($rules, $customMessages);

        // execute onSave callback
        $proceed = is_null($onSave) ? $this->onSave() : $onSave($this);

        // save if all conditions are satisfied
        return ($proceed && $validated) ? parent::save() : false;

    }

    /**
     * Force save the model even if validation fails.
     *
     * @param array $rules:array
     * @param array $customMessages:array
     * @return bool
     */
    public function force_save($rules = array(), $customMessages = array(), Closure $onForceSave = null)
    {

        // validate the model
        $this->validate($rules, $customMessages);

        // execute onForceSave callback
        $proceed = is_null($onForceSave) ? $this->onForceSave() : $onForceSave($this);

        // save regardless of the outcome of validation
        return $proceed ? parent::save() : false;

    }

}