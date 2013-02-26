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
use Illuminate\Support\Facades\Hash;
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
     * By default, Ardent will attempt hydration only if the model object contains no attributes and
     * the $autoHydrateEntityFromInput property is set to true.
     * Setting $forceEntityHydrationFromInput to true will bypass the above check and enforce
     * hydration of model attributes.
     *
     * @var bool
     */
    public $forceEntityHydrationFromInput = false;

    /**
     * If set to true, the object will automatically remove redundant model
     * attributes (i.e. confirmation fields).
     *
     * @var bool
     */
    public $autoPurgeRedundantAttributes = false;

    /**
     * Array of closure functions which determine if a given attribute is deemed
     * redundant (and should not be persisted in the database)
     *
     * @var array
     */
    protected $purgeFilters = array();

    protected $purgeFiltersInitialized = false;

    /**
     * List of attribute names which should be hashed using the Bcrypt hashing algorithm.
     *
     * @var array
     */
    public static $passwordAttributes = array();

    /**
     * If set to true, the model will automatically replace all plain-text passwords
     * attributes (listed in $passwordAttributes) with hash checksums
     *
     * @var bool
     */
    public $autoHashPasswordAttributes = false;

    /**
     * Create a new Ardent model instance.
     *
     * @param array   $attributes
     * @return \LaravelBook\Ardent\Ardent
     */
    public function __construct( array $attributes = array() ) {

        parent::__construct( $attributes );
        $this->validationErrors = new MessageBag;
    }

    /**
     * Determine if a given string ends with a given needle.
     *
     * @param string  $haystack
     * @param string  $needle
     * @return bool
     */
    protected function endsWith( $haystack, $needle ) {
        return $needle == substr( $haystack, strlen( $haystack ) - strlen( $needle ) );
    }

    /**
     * Validate the model instance
     *
     * @param array   $rules          Validation rules
     * @param array   $customMessages Custom error messages
     * @return bool
     */
    public function validate( $rules = array(), $customMessages = array() ) {

        // check for overrides, then remove any empty rules
        $rules = ( empty( $rules ) ) ? static::$rules : $rules;
        foreach ( $rules as $field => $rls ) {
            if ( $rls == '' ) {
                unset( $rules[$field] );
            }
        }

        $customMessages = ( empty( $customMessages ) ) ? static::$customMessages : $customMessages;

        if ( $this->forceEntityHydrationFromInput || ( empty( $this->attributes ) && $this->autoHydrateEntityFromInput ) ) {
            // pluck only the fields which are defined in the validation rule-set
            $attributes = array_intersect_key( Input::all(), $rules );
            
            //Set each given attribute on the model
            foreach ($attributes as $key => $value){
            	$this->setAttribute($key, $value);
            }
        }

        $data = $this->attributes; // the data under validation

        $success = empty( $data ) && empty( $rules );

        if ( !empty( $data ) && !empty( $rules ) ) {

            // perform validation
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
                if ( Input::hasSessionStore() ) {
                    Input::flash();
                }
            }

        }

        return $success;
    }

    /**
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * @param bool    $forced Indicates whether the model is being saved forcefully
     * @return bool
     */
    protected function beforeSave( $forced = false ) {
        return true;
    }

    /**
     * Called after a model is successfully saved.
     *
     * @param bool    $success Indicates whether the database save operation succeeded
     * @param bool    $forced  Indicates whether the model is being saved forcefully
     * @return void
     */
    protected function afterSave( $success, $forced = false ) {
        //
    }

    /**
     * Save the model to the database.
     *
     * @param array   $rules:array
     * @param array   $customMessages
     * @param closure $beforeSave
     * @param callable $afterSave
     * @return bool
     */
    public function save( $rules = array(), $customMessages = array(), Closure $beforeSave = null, Closure $afterSave = null ) {

        // validate
        $validated = $this->validate( $rules, $customMessages );

        // execute beforeSave callback
        $proceed = is_null( $beforeSave ) ? $this->beforeSave( false ) : $beforeSave( $this );

        // attempt to save if all conditions are satisfied
        $success = ( $proceed && $validated ) ? $this->performSave() : false;

        is_null( $afterSave ) ? $this->afterSave( $success, false ) : $afterSave( $this );

        return $success;
    }

    /**
     * Force save the model even if validation fails.
     *
     * @param array   $rules:array
     * @param array   $customMessages:array
     * @param callable $beforeSave
     * @param callable $afterSave
     * @return bool
     */
    public function forceSave( $rules = array(), $customMessages = array(), Closure $beforeSave = null, Closure $afterSave = null ) {

        // validate the model
        $this->validate( $rules, $customMessages );

        // execute beforeForceSave callback
        $proceed = is_null( $beforeSave ) ? $this->beforeSave( true ) : $beforeSave( $this );

        // attempt to save regardless of the outcome of validation
        $success = $proceed ? $this->performSave() : false;

        is_null( $afterSave ) ? $this->afterSave( $success, true ) : $afterSave( $this );

        return $success;
    }

    /**
     * Add the basic purge filters
     *
     * @return void
     */
    protected function addBasicPurgeFilters() {
        if ( $this->purgeFiltersInitialized ) return;

        $this->purgeFilters[] = function ( $attributeKey ) {
            // disallow password confirmation fields
            if ( $this->endsWith( $attributeKey, '_confirmation' ) )
                return false;

            // "_method" is used by Illuminate\Routing\Router to simulate custom HTTP verbs
            if ( strcmp( $attributeKey, '_method' ) === 0 )
                return false;

            return true;
        };

        $this->purgeFiltersInitialized = true;
    }

    /**
     * Removes redundant attributes from model
     *
     * @param array   $array Input array
     * @return array
     */
    protected function purgeArray( array $array = array() ) {

        $result = array();
        $keys = array_keys( $array );

        $this->addBasicPurgeFilters();

        if ( !empty( $keys ) && !empty( $this->purgeFilters ) ) {
            foreach ( $keys as $key ) {
                $allowed = true;

                foreach ( $this->purgeFilters as $filter ) {
                    $allowed = $filter( $key );

                    if ( !$allowed )
                        break;
                }

                if ( $allowed ) {
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

        if ( $this->autoPurgeRedundantAttributes ) {
            $this->attributes = $this->purgeArray( $this->attributes );
        }

        if ( $this->autoHashPasswordAttributes ) {
            $this->attributes = $this->hashPasswordAttributes( $this->attributes, static::$passwordAttributes );
        }

        return parent::save();
    }

    /**
     * Get validation error message collection for the Model
     *
     * @return Illuminate\Support\MessageBag
     */
    public function errors() {
        return $this->validationErrors;
    }

    /**
     * Automatically replaces all plain-text password attributes (listed in $passwordAttributes)
     * with hash checksum.
     *
     * @param array   $attributes
     * @param array   $passwordAttributes
     * @return array
     */
    protected function hashPasswordAttributes( array $attributes = array(), array $passwordAttributes = array() ) {

        if ( empty( $passwordAttributes ) || empty( $attributes ) )
            return $attributes;

        $result = array();
        foreach ( $attributes as $key => $value ) {

            if ( in_array( $key, $passwordAttributes ) && !is_null( $value ) ) {
                $result[$key] = Hash::make( $value );
            }
            else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

}
