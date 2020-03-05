<?php namespace LaravelArdent\Ardent;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DatabaseCapsule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Events\Dispatcher;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\MessageBag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory as ValidationFactory;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Ardent - Self-validating Eloquent model base class
 *
 */
abstract class Ardent extends Model {

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
     * The array of custom attributes.
     *
     * @var array
     */
    public static $customAttributes = array();

	/**
     * The validator object in case you need it externally (say, for a form builder).
     *
     * @see getValidator()
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * The message bag instance containing validation error messages
     *
     * @var \Illuminate\Support\MessageBag
     */
    public $validationErrors;

    /**
     * Makes the validation procedure throw an {@link InvalidModelException} instead of returning
     * false when validation fails.
     *
     * @var bool
     */
    public $throwOnValidation = false;

    /**
     * Forces the behavior of findOrFail in very find method - throwing a {@link ModelNotFoundException}
     * when the model is not found.
     *
     * @var bool
     */
    public static $throwOnFind = false;

    /**
     * If set to true, the object will automatically populate model attributes from request()->all()
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
    public static $passwordAttributes = array('password');

    /**
     * If set to true, the model will automatically replace all plain-text passwords
     * attributes (listed in $passwordAttributes) with hash checksums
     *
     * @var bool
     */
    public $autoHashPasswordAttributes = false;

    /**
     * If set to true will try to instantiate other components as if it was outside Laravel.
     *
     * @var bool
     */
    protected static $external = false;

    /**
     * A Validation Factory instance, to be used by standalone Ardent instances with the Translator.
     *
     * @var \Illuminate\Validation\Factory
     */
    protected static $validationFactory;

    /**
     * An instance of a Hasher object, to be used by standalone Ardent instances. Will be null if not external.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     * @see LaravelArdent\Ardent\Ardent::configureAsExternal()
     */
    public static $hasher;

    /**
     * Can be used to ease declaration of relationships in Ardent models.
     * Follows closely the behavior of the relation methods used by Eloquent, but packing them into an indexed array
     * with relation constants make the code less cluttered.
     *
     * It should be declared with camel-cased keys as the relation name, and value being a mixed array with the
     * relation constant being the first (0) value, the second (1) being the classname and the next ones (optionals)
     * having named keys indicating the other arguments of the original methods: 'foreignKey' (belongsTo, hasOne,
     * belongsToMany and hasMany); 'table' and 'otherKey' (belongsToMany only); 'name', 'type' and 'id' (specific for
     * morphTo, morphOne and morphMany).
     * Exceptionally, the relation type MORPH_TO does not include a classname, following the method declaration of
     * {@link \Illuminate\Database\Eloquent\Model::morphTo}.
     *
     * Example:
     * <code>
     * class Order extends Ardent {
     *     protected static $relations = array(
     *         'items'    => array(self::HAS_MANY, 'Item'),
     *         'owner'    => array(self::HAS_ONE, 'User', 'foreignKey' => 'user_id'),
     *         'pictures' => array(self::MORPH_MANY, 'Picture', 'name' => 'imageable')
     *     );
     * }
     * </code>
     *
     * @see \Illuminate\Database\Eloquent\Model::hasOne
     * @see \Illuminate\Database\Eloquent\Model::hasMany
     * @see \Illuminate\Database\Eloquent\Model::hasManyThrough
     * @see \Illuminate\Database\Eloquent\Model::belongsTo
     * @see \Illuminate\Database\Eloquent\Model::belongsToMany
     * @see \Illuminate\Database\Eloquent\Model::morphTo
     * @see \Illuminate\Database\Eloquent\Model::morphOne
     * @see \Illuminate\Database\Eloquent\Model::morphMany
     * @see \Illuminate\Database\Eloquent\Model::morphToMany
     * @see \Illuminate\Database\Eloquent\Model::morphedByMany
     *
     * @var array
     */
    protected static $relationsData = array();

    /** This class "has one model" if its ID is an FK in that model */
    const HAS_ONE = 'hasOne';

    /** This class "has many models" if its ID is an FK in those models */
    const HAS_MANY = 'hasMany';

    const HAS_MANY_THROUGH = 'hasManyThrough';

    /** This class "belongs to a model" if it has a FK from that model */
    const BELONGS_TO = 'belongsTo';

    const BELONGS_TO_MANY = 'belongsToMany';

    const MORPH_TO = 'morphTo';

    const MORPH_ONE = 'morphOne';

    const MORPH_MANY = 'morphMany';

    const MORPH_TO_MANY = 'morphToMany';

    const MORPHED_BY_MANY = 'morphedByMany';

    /**
     * Array of relations used to verify arguments used in the {@link $relationsData}
     *
     * @var array
     */
    protected static $relationTypes = array(
        self::HAS_ONE, self::HAS_MANY, self::HAS_MANY_THROUGH,
        self::BELONGS_TO, self::BELONGS_TO_MANY,
        self::MORPH_TO, self::MORPH_ONE, self::MORPH_MANY,
        self::MORPH_TO_MANY, self::MORPHED_BY_MANY
    );

    /**
     * Create a new Ardent model instance.
     *
     * @param array $attributes
     * @return \LaravelArdent\Ardent\Ardent
     */
    public function __construct(array $attributes = array()) {
        parent::__construct($attributes);
        $this->validationErrors = new MessageBag;
    }

    /**
     * The "booting" method of the model.
     * Overrided to attach before/after method hooks into the model events.
     *
     * @see \Illuminate\Database\Eloquent\Model::boot()
     * @return void
     */
    public static function boot() {
        parent::boot();

        $myself   = get_called_class();
        $hooks    = array('before' => 'ing', 'after' => 'ed');
        $radicals = array('sav', 'validat', 'creat', 'updat', 'delet');

        foreach ($radicals as $rad) {
            foreach ($hooks as $hook => $event) {
                $method = $hook.ucfirst($rad).'e';
                if (method_exists($myself, $method)) {
                    $eventMethod = $rad.$event;
                    self::$eventMethod(function($model) use ($method){
                        return $model->$method($model);
                    });
                }
            }
        }
    }

	public function getObservableEvents() {
		return array_merge(
			parent::getObservableEvents(),
			array('validating', 'validated')
		);
	}

	/**
	 * Register a validating model event with the dispatcher.
	 *
	 * @param Closure|string $callback
	 * @return void
	 */
	public static function validating($callback) {
		static::registerModelEvent('validating', $callback);
	}

	/**
	 * Register a validated model event with the dispatcher.
	 *
	 * @param Closure|string $callback
	 * @return void
	 */
	public static function validated($callback) {
		static::registerModelEvent('validated', $callback);
	}

    /**
     * Looks for the relation in the {@link $relationsData} array and does the correct magic as Eloquent would require
     * inside relation methods. For more information, read the documentation of the mentioned property.
     *
     * @param string $relationName the relation key, camel-case version
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     * @throws \InvalidArgumentException when the first param of the relation is not a relation type constant,
     *      or there's one or more arguments missing
     * @see Ardent::relationsData
     */
    protected function handleRelationalArray($relationName) {
        $relation     = static::$relationsData[$relationName];
        $relationType = $relation[0];
        $errorHeader  = "Relation '$relationName' on model '".get_called_class();

        if (!in_array($relationType, static::$relationTypes)) {
            throw new \InvalidArgumentException($errorHeader.
            ' should have as first param one of the relation constants of the Ardent class.');
        }
        if (!isset($relation[1]) && $relationType != self::MORPH_TO) {
            throw new \InvalidArgumentException($errorHeader.
            ' should have at least two params: relation type and classname.');
        }
        if (isset($relation[1]) && $relationType == self::MORPH_TO) {
            throw new \InvalidArgumentException($errorHeader.
            ' is a morphTo relation and should not contain additional arguments.');
        }

        $verifyArgs = function (array $opt, array $req = array()) use ($relationName, &$relation, $errorHeader) {
            $missing = array('req' => array(), 'opt' => array());

            foreach (array('req', 'opt') as $keyType) {
                foreach ($$keyType as $key) {
                    if (!array_key_exists($key, $relation)) {
                        $missing[$keyType][] = $key;
                    }
                }
            }

            if ($missing['req']) {
                throw new \InvalidArgumentException($errorHeader.'
                    should contain the following key(s): '.join(', ', $missing['req']));
            }
            if ($missing['opt']) {
                foreach ($missing['opt'] as $include) {
                    $relation[$include] = null;
                }
            }
        };

        switch ($relationType) {
            case self::HAS_ONE:
            case self::HAS_MANY:
                $verifyArgs(['foreignKey', 'localKey']);
                return $this->$relationType($relation[1], $relation['foreignKey'], $relation['localKey']);

            case self::HAS_MANY_THROUGH:
                $verifyArgs(['firstKey', 'secondKey', 'localKey'], ['through']);
                return $this->$relationType($relation[1], $relation['through'], $relation['firstKey'], $relation['secondKey'], $relation['localKey']);

            case self::BELONGS_TO:
                $verifyArgs(['foreignKey', 'otherKey', 'relation']);
                return $this->$relationType($relation[1], $relation['foreignKey'], $relation['otherKey'], $relation['relation']);

            case self::BELONGS_TO_MANY:
                $verifyArgs(['table', 'foreignKey', 'otherKey', 'relation']);
                $relationship = $this->$relationType($relation[1], $relation['table'], $relation['foreignKey'], $relation['otherKey'], $relation['relation']);
                if(isset($relation['pivotKeys']) && is_array($relation['pivotKeys'])) {
                    $relationship->withPivot($relation['pivotKeys']);
                }
                if(isset($relation['timestamps']) && $relation['timestamps']) {
                    $relationship->withTimestamps();
                }
                return $relationship;

            case self::MORPH_TO:
                $verifyArgs(['name', 'type', 'id']);
                return $this->$relationType($relation['name'], $relation['type'], $relation['id']);

            case self::MORPH_ONE:
            case self::MORPH_MANY:
                $verifyArgs(['type', 'id', 'localKey'], ['name']);
                return $this->$relationType($relation[1], $relation['name'], $relation['type'], $relation['id'], $relation['localKey']);

            case self::MORPH_TO_MANY:
                $verifyArgs(['table', 'foreignKey', 'otherKey', 'inverse'], ['name']);
                return $this->$relationType($relation[1], $relation['name'], $relation['table'], $relation['foreignKey'], $relation['otherKey'], $relation['inverse']);

            case self::MORPHED_BY_MANY:
                $verifyArgs(['table', 'foreignKey', 'otherKey'], ['name']);
                return $this->$relationType($relation[1], $relation['name'], $relation['table'], $relation['foreignKey'], $relation['otherKey']);
        }
    }

    /**
     * Handle dynamic method calls into the method.
     * Overrided from {@link Eloquent} to implement recognition of the {@link $relationsData} array.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return Relation|Builder|mixed
     */
    public function __call($method, $parameters) {
        if (array_key_exists($method, static::$relationsData)) {
            return $this->handleRelationalArray($method);
        }

        return parent::__call($method, $parameters);
    }


	/**
	 * Define an inverse one-to-one or many relationship.
	 * Overriden from {@link Eloquent\Model} to allow the usage of the intermediary methods to handle the {@link
	 * $relationsData} array.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function belongsTo($related, $foreignKey = NULL, $otherKey = NULL, $relation = NULL) {

		// If no foreign key was supplied, we can use a backtrace to guess the proper
		// foreign key name by using the name of the relationship function, which
		// when combined with an "_id" should conventionally match the columns.
		if (is_null($relation)) {
			$backtrace = debug_backtrace(false, 4);
			if ($backtrace[1]['function'] == 'handleRelationalArray') {
				$relation = $backtrace[1]['args'][0];
			} else {
				$relation = $backtrace[3]['function'];
			}
		}

		if (is_null($foreignKey)) {
			$foreignKey = Str::snake($relation).'_id';
		}

		// Once we have the foreign key names, we'll just create a new Eloquent query
		// for the related models and returns the relationship instance which will
		// actually be responsible for retrieving and hydrating every relations.
		$instance = new $related;
		
		$otherKey = $otherKey ?: $instance->getKeyName();
		
		$query = $instance->newQuery();

		return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
	}

	/**
	 * Define an polymorphic, inverse one-to-one or many relationship.
	 * Overriden from {@link Eloquent\Model} to allow the usage of the intermediary methods to handle the {@link
	 * $relationsData} array.
	 *
	 * @param  string  $name
	 * @param  string  $type
	 * @param  string  $id
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function morphTo($name = null, $type = null, $id = null, $ownerKey = null) {
		// If no name is provided, we will use the backtrace to get the function name
		// since that is most likely the name of the polymorphic interface. We can
		// use that to get both the class and foreign key that will be utilized.
		if (is_null($name))
		{
			$backtrace = debug_backtrace(false);
			$caller = ($backtrace[1]['function'] == 'handleRelationalArray')? $backtrace[3] : $backtrace[1];

			$name = Str::snake($caller['function']);
		}

		// Next we will guess the type and ID if necessary. The type and IDs may also
		// be passed into the function so that the developers may manually specify
		// them on the relations. Otherwise, we will just make a great estimate.
		list($type, $id) = $this->getMorphs($name, $type, $id);

		$class = $this->$type;

		return $this->belongsTo($class, $id);
	}

    /**
     * Get an attribute from the model.
     * Overrided from {@link Eloquent} to implement recognition of the {@link $relationsData} array.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key) {
        $attr = parent::getAttribute($key);

        if ($attr === null) {
            $camelKey = Str::camel($key);
            if (array_key_exists($camelKey, static::$relationsData)) {
                $this->relations[$key] = $this->$camelKey()->getResults();
                return $this->relations[$key];
            }
        }

        return $attr;
    }

    /**
     * Configures Ardent to be used outside of Laravel - correctly setting Eloquent and Validation modules.
     * @todo Should allow for additional language files. Would probably receive a Translator instance as an optional argument, or a list of translation files.
     *
     * @param array $connection Connection info used by {@link \Illuminate\Database\Capsule\Manager::addConnection}.
     * Should contain driver, host, port, database, username, password, charset and collation.
     */
    public static function configureAsExternal(array $connection, $lang = 'en') {
        $db = new DatabaseCapsule;
        $db->addConnection($connection);
        $db->setEventDispatcher(new Dispatcher(new Container));
        //TODO: configure a cache manager (as an option)

        // Make this Capsule instance available globally via static methods
        $db->setAsGlobal();
        
        $db->bootEloquent();

        $translator = new Translator($lang);
        $translator->addLoader('file_loader', new PhpFileLoader());
        $translator->addResource('file_loader',
            dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.$lang.
            DIRECTORY_SEPARATOR.'validation.php', $lang);

        self::$external = true;
        self::$validationFactory = new ValidationFactory($translator);
        self::$validationFactory->setPresenceVerifier(new DatabasePresenceVerifier($db->getDatabaseManager()));

        self::$hasher = new BcryptHasher();
    }

    /**
     * Instatiates the validator used by the validation process, depending if the class is being used inside or
     * outside of Laravel.
     *
     * @param $data
     * @param $rules
     * @param $customMessages
     * @param $customAttributes
     * @return \Illuminate\Validation\Validator
     * @see Ardent::$externalValidator
     */
    protected static function makeValidator($data, $rules, $customMessages, $customAttributes) {
        return self::$external?
            self::$validationFactory->make($data, $rules, $customMessages, $customAttributes) :
            Validator::make($data, $rules, $customMessages, $customAttributes);
    }

    /**
     * Validate the model instance
     *
     * @param array $rules            Validation rules
     * @param array $customMessages   Custom error messages
     * @param array $customAttributes Custom attributes
     * @return bool
     * @throws InvalidModelException
     */
    public function validate(array $rules = array(), array $customMessages = array(), array $customAttributes = array()) {
        if ($this->fireModelEvent('validating') === false) {
            if ($this->throwOnValidation) {
                throw new InvalidModelException($this);
            } else {
                return false;
            }
        }

        // check for overrides, then remove any empty rules
        $rules = (empty($rules))? static::$rules : $rules;
        foreach ($rules as $field => $rls) {
            if ($rls == '') {
                unset($rules[$field]);
            }
        }

        if (empty($rules)) {
            $success = true;
        } else {
			$customMessages = (empty($customMessages))? static::$customMessages : $customMessages;
			$customAttributes = (empty($customAttributes))? static::$customAttributes : $customAttributes;

			if ($this->forceEntityHydrationFromInput || (empty($this->attributes) && $this->autoHydrateEntityFromInput)) {
				$this->fill(request()->all());
			}

			$data = $this->getAttributes(); // the data under validation

			// perform validation
			$this->validator = static::makeValidator($data, $rules, $customMessages, $customAttributes);
			$success   = $this->validator->passes();

			if ($success) {
				// if the model is valid, unset old errors
				if ($this->validationErrors === null || $this->validationErrors->count() > 0) {
					$this->validationErrors = new MessageBag;
				}
			} else {
				// otherwise set the new ones
				$this->validationErrors = $this->validator->messages();

				// stash the input to the current session
                                if (!self::$external && request()->hasSession()) {
                                        request()->flash();
				}
			}
		}

        $this->fireModelEvent('validated', false);

	    if (!$success && $this->throwOnValidation) {
		    throw new InvalidModelException($this);
	    }

        return $success;
    }

    /**
     * Save the model to the database. Is used by {@link save()} and {@link forceSave()} as a way to DRY code.
     *
     * @param array   $rules
     * @param array   $customMessages
     * @param array   $options
     * @param Closure $beforeSave
     * @param Closure $afterSave
     * @param bool    $force          Forces saving invalid data.
     * 
     * @return bool
     * @see Ardent::save()
     * @see Ardent::forceSave()
     */
    protected function internalSave(array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null,
        $force = false
    ) {
        if ($beforeSave) {
            self::saving($beforeSave);
        }
        if ($afterSave) {
            self::saved($afterSave);
        }

        $valid = $this->validateUniques($rules, $customMessages);

        if ($force || $valid) {
            return $this->performSave($options);
        } else {
            return false;
        }
    }

    /**
     * Save the model to the database.
     *
     * @param array   $rules
     * @param array   $customMessages
     * @param array   $options
     * @param Closure $beforeSave
     * @param Closure $afterSave
     *
     * @return bool
     * @see Ardent::forceSave()
     */
    public function save(array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null
    ) {
        return $this->internalSave($rules, $customMessages, $options, $beforeSave, $afterSave, false);
    }

    /**
     * Force save the model even if validation fails.
     *
     * @param array   $rules
     * @param array   $customMessages
     * @param array   $options
     * @param Closure $beforeSave
     * @param Closure $afterSave
     * @return bool
     * @see Ardent::save()
     */
    public function forceSave(array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null
    ) {
        return $this->internalSave($rules, $customMessages, $options, $beforeSave, $afterSave, true);
    }


    /**
     * Add the basic purge filters
     *
     * @return void
     */
    protected function addBasicPurgeFilters() {
        if ($this->purgeFiltersInitialized) {
            return;
        }

        $this->purgeFilters[] = function ($attributeKey) {
            // disallow password confirmation fields
            if (Str::endsWith($attributeKey, '_confirmation')) {
                return false;
            }

            // "_method" is used by Illuminate\Routing\Router to simulate custom HTTP verbs
            if (strcmp($attributeKey, '_method') === 0) {
                return false;
            }

            // "_token" is used by Illuminate\Html\FormBuilder to add CSRF protection
            if (strcmp($attributeKey, '_token') === 0) {
                return false;
            }

            return true;
        };

        $this->purgeFiltersInitialized = true;
    }

    /**
     * Removes redundant attributes from model
     *
     * @param array $array Input array
     * @return array
     */
    protected function purgeArray(array $array = array()) {

        $result = array();
        $keys   = array_keys($array);

        $this->addBasicPurgeFilters();

        if (!empty($keys) && !empty($this->purgeFilters)) {
            foreach ($keys as $key) {
                $allowed = true;

                foreach ($this->purgeFilters as $filter) {
                    $allowed = $filter($key);

                    if (!$allowed) {
                        break;
                    }
                }

                if ($allowed) {
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
     * @param array $options
     * @return bool
     */
    protected function performSave(array $options) {

        if ($this->autoPurgeRedundantAttributes) {
            $this->attributes = $this->purgeArray($this->getAttributes());
        }

        if ($this->autoHashPasswordAttributes) {
            $this->attributes = $this->hashPasswordAttributes($this->getAttributes(), static::$passwordAttributes);
        }

        return parent::save($options);
    }

    /**
     * Get validation error message collection for the Model
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function errors() {
        return $this->validationErrors;
    }

    /**
     * Hashes the password, working without the Hash facade if this is an instance outside of Laravel.
     * @param $value
     * @return string
     */
    protected function hashPassword($value) {
        return self::$external? self::$hasher->make($value) : Hash::make($value);
    }

    /**
     * Automatically replaces all plain-text password attributes (listed in $passwordAttributes)
     * with hash checksum.
     *
     * @param array $attributes
     * @param array $passwordAttributes
     * @return array
     */
    protected function hashPasswordAttributes(array $attributes = array(), array $passwordAttributes = array()) {

        if (empty($passwordAttributes) || empty($attributes)) {
            return $attributes;
        }

        $result = array();
        foreach ($attributes as $key => $value) {

            if (in_array($key, $passwordAttributes) && !is_null($value)) {
                if ($value != $this->getOriginal($key)) {
                    $result[$key] = $this->hashPassword($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Appends the model ID to the 'unique' rules given. The resulting array can
     * then be fed to a Ardent save so that unchanged values don't flag a validation
     * issue. It can also be used with {@link Illuminate\Foundation\Http\FormRequest}
     * to painlessly validate model requests.
     * Rules can be in either strings with pipes or arrays, but the returned rules
     * are in arrays.
     * @param array $rules
     * @return array Rules with exclusions applied
     */
    public function buildUniqueExclusionRules(array $rules = array()) {
      
        if (!count($rules))
          $rules = static::$rules;

        foreach ($rules as $field => &$ruleset) {
            // If $ruleset is a pipe-separated string, switch it to array
            $ruleset = (is_string($ruleset))? explode('|', $ruleset) : $ruleset;

            foreach ($ruleset as &$rule) {
                if (strpos($rule, 'unique:') === 0) {
                    // Stop splitting at 4 so final param will hold optional where clause
                    $params = explode(',', $rule, 4);

                    $uniqueRules = array();

                    // Append table name if needed
                    $table = explode(':', $params[0]);
                    if (count($table) == 1) {
                        $uniqueRules[1] = $this->getTable();
                    } else {
                        $uniqueRules[1] = $table[1];
                    }

                    // Append field name if needed
                    if (count($params) == 1) {
                        $uniqueRules[2] = $field;
                    } else {
                        $uniqueRules[2] = $params[1];
                    }

                    if (isset($this->primaryKey)) {
                        if (isset($this->{$this->primaryKey})) {
                            $uniqueRules[3] = $this->{$this->primaryKey};

                            // If optional where rules are passed, append them otherwise use primary key
                            $uniqueRules[4] = isset($params[3])? $params[3] : $this->primaryKey;
                        }
                    } else {
                        if (isset($this->id)) {
                            $uniqueRules[3] = $this->id;
                        }
                    }

                    $rule = 'unique:'.implode(',', $uniqueRules);
                }
            }
        }
        
        return $rules;
    }

    /**
     * Update a model, but filter uniques first to ensure a unique validation rule
     * does not fire
     *
     * @param array   $rules
     * @param array   $customMessages
     * @param array   $options
     * @param Closure $beforeSave
     * @param Closure $afterSave
     * @return bool
     */
    public function updateUniques(array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null
    ) {
        $rules = $this->buildUniqueExclusionRules($rules);
        
        return $this->save($rules, $customMessages, $options, $beforeSave, $afterSave);
    }

	/**
	 * Validates a model with unique rules properly treated.
	 *
	 * @param array $rules Validation rules
	 * @param array $customMessages Custom error messages
	 * @return bool
	 * @see Ardent::validate()
	 */
	public function validateUniques(array $rules = array(), array $customMessages = array()) {
		$rules = $this->buildUniqueExclusionRules($rules);
		return $this->validate($rules, $customMessages);
	}

    /**
     * Find a model by its primary key.
     * If {@link $throwOnFind} is set, will use {@link findOrFail} internally.
     *
     * @param  mixed $id
     * @param  array $columns
     * @return Ardent|Collection
     */
    public static function find($id, $columns = array('*')) {
        $debug = debug_backtrace(false);

        if (static::$throwOnFind && $debug[1]['function'] != 'findOrFail') {
            return self::findOrFail($id, $columns);
        } else {
            //mimicking Eloquent's __callStatic() + __call() behaviour so we don't loop forever
            return (new static)->__call('find', [$id, $columns]);
        }
    }

	/**
	 * Get a new query builder for the model's table.
	 * Overriden from {@link \Model\Eloquent} to allow for usage of {@link throwOnFind} in our {@link Builder}.
	 *
	 * @see Model::newQueryWithoutScopes()
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function newQueryWithoutScopes() {
		$builder = new Builder($this->newBaseQueryBuilder());
		$builder->throwOnFind = static::$throwOnFind;

		return $builder->setModel($this)->with($this->with);
	}

    /**
     * Returns the validator object created after {@link validate()}.
     * @return \Illuminate\Validation\Validator
     */
    public function getValidator() {
        return $this->validator;
    }
}
