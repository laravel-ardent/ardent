<?php namespace LaravelBook\Ardent;

/*
 * This file is part of the Ardent package.
 *
 * (c) Max Ehsan <contact@laravelbook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


use Closure;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;

/**
 * Ardent - Self-validating Eloquent model base class
 *
 */
abstract class Ardent extends Model
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
     * If set to true, the object will automatically populate model attributes from Input::all()
     *
     * @var bool
     */
    public $autoHydrateEntityFromInput = false;

    /**
     * List of attribute names which should be hashed using the Bcrypt hashing algorithm.
     *
     * @var array
     */
    public $passwordAttributes = array();

    /**
     * If set to true, the model will automatically replace all plain-text passwords
     * attributes (listed in $passwordAttributes) with hash checksums
     *
     * @var bool
     */
    public $autoHashPasswordAttributes = true;

    /**
     * Create a new Ardent model instance.
     *
     * @param array   $attributes
     * @return void
     */
    public function __construct( array $attributes = array() ) {
        parent::__construct( $attributes );
        $this->validationErrors = new MessageBag;
    }

    /**
     * Validate the model instance
     *
     * @param array   $rules          Validation rules
     * @param array   $customMessages Custom error messages
     * @return bool
     */
    public function validate( $rules = array(), $customMessages = array() ) {

        $success = true;

        if ( empty( $this->attributes ) && $this->autoHydrateEntityFromInput ) {
            // pluck only the fields which are defined in the validation rule-set
            $this->attributes = array_intersect_key( Input::all(), $rules );
        }

        $data = $this->attributes; // the data under validation

        if ( !empty( $data ) && ( !empty( $rules ) || !empty( static::$rules ) ) ) {

            // check for overrides
            $rules = ( empty( $rules ) ) ? static::$rules : $rules;
            $customMessages = ( empty( $customMessages ) ) ? static::$customMessages : $customMessages;

            // construct the validator
            $validator = Validator::make( $data, $rules, $customMessages );
            $success = $validator->passes();

            if ( $success ) {
                // if the model is valid, unset old errors
                if ( $this->validationErrors->count() > 0 ) {
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
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * @return bool
     */
    protected function beforeSave() {
        return true;
    }

    /**
     * Called after a model is successfully saved.
     *
     * @param bool    $success Indicates whether the database save operation succeeded
     * @return void
     */
    public function afterSave( $success ) {
        //
    }

    /**
     * Invoked before a model is saved forcefully. Return false to abort the operation.
     *
     * @return bool
     */
    protected function beforeForceSave() {
        return true;
    }

    /**
     * Called after a model is successfully force-saved.
     *
     * @param bool    $success Indicates whether the database save operation succeeded
     * @return void
     */
    public function afterForceSave( $success ) {
        //
    }

    /**
     * Save the model to the database.
     *
     * @param array   $rules:array
     * @param array   $customMessages
     * @param closure $beforeSave
     * @return bool
     */
    public function save( $rules = array(), $customMessages = array(), Closure $beforeSave = null, Closure $afterSave = null ) {

        // validate
        $validated = $this->validate( $rules, $customMessages );

        // execute beforeSave callback
        $proceed = is_null( $beforeSave ) ? $this->beforeSave() : $beforeSave( $this );

        // attempt to save if all conditions are satisfied
        $success = ( $proceed && $validated ) ? $this->performSave() : false;

        is_null( $afterSave ) ? $this->afterSave( $success ) : $afterSave( $this );

        return $success;
    }

    /**
     * Force save the model even if validation fails.
     *
     * @param array   $rules:array
     * @param array   $customMessages:array
     * @return bool
     */
    public function forceSave( $rules = array(), $customMessages = array(), Closure $beforeForceSave = null, Closure $afterForceSave = null ) {

        // validate the model
        $this->validate( $rules, $customMessages );

        // execute beforeForceSave callback
        $proceed = is_null( $beforeForceSave ) ? $this->beforeForceSave() : $beforeForceSave( $this );

        // attempt to save regardless of the outcome of validation
        $success = $proceed ? $this->performSave() : false;

        is_null( $afterForceSave ) ? $this->afterForceSave( $success ) : $afterForceSave( $this );

        return $success;
    }

    /**
     * Removes *_confirmation fields from array
     *
     * @param array   $array Input array
     * @return array
     */
    protected function purgeArray( array $array = array() ) {

        $result = array();
        $keys = array_keys( $array );

        if ( !empty( $keys ) ) {
            $len = strlen( '_confirmation' );

            foreach ( $keys as $key ) {
                if ( strlen( $key ) > $len ) {
                    if ( strcmp( substr( $key, -$len ), '_confirmation' ) != 0 ) {
                        $result[$key] = $array[$key];
                    }
                } else {
                    $result[$key] = $array[$key];
                }
            }
        }

        return $result;
    }

    /**
     * Saves the model instance to database. If necessary, it will purge the model attributes
     * of unnecessary fields. It will also replace plain-text password fields with their hashes.
     *
     * @return bool
     */
    protected function performSave() {
        if ( $this->autoHydrateEntityFromInput ) {
            $this->attributes = $this->purgeArray( $this->attributes );
        }

        $this->hashPasswordAttributes();

        return parent::save();
    }

    /**
     * Get all validation error messages for the Model
     *
     * @return Illuminate\Support\MessageBag
     */
    public function getErrors() {
        return $this->validationErrors;
    }

    /**
     * Automatically replaces all plain-text password attributes (listed in $passwordAttributes)
     * with hash checksums.
     *
     * @return void
     */
    protected function hashPasswordAttributes() {

        if ( !$this->autoHashPasswordAttributes
            || empty( $this->passwordAttributes )
            || empty( $this->attributes ) )
            return;

        $data = $this->attributes;
        foreach ( $data as $key => $value ) {
            if ( array_key_exists( $key, $this->passwordAttributes ) && !is_null( $value ) ) {
                $this->attributes[$key] = Hash::make( $value );
            }
        }
    }

}
