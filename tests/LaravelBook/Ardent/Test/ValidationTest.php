<?php namespace LaravelBook\Ardent\Test;

use LaravelBook\Ardent\Ardent;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Mockery as m;

class ValidationTest extends TestCase
{
    public function testValidateCalledOnSave()
    {
        $model = m::mock('LaravelBook\Ardent\Test\ValidatingModel[validate]');

        $model->shouldReceive('validate')->once()
            ->andReturn(false);

        $model->save();
    }

    public function testValidationFailurePreventsSave()
    {
        $model = m::mock('LaravelBook\Ardent\Test\ValidatingModel[validate]');

        $model->shouldReceive('validate')
            ->andReturn(false);

        $model->save();

        $this->assertEquals(0, $model->saveCalled);
    }

    /**
     * @expectedException LaravelBook\Ardent\InvalidModelException
     */
    public function testValidationThrowsWhenConfigured()
    {
        $model = new ValidatingModel;
        $model->throwOnValidation = true;

        $this->validator->shouldReceive('passes')
            ->andReturn(false);

        $model->validate();
    }

    public function testValidationSuccessAllowsSave()
    {
        $model = m::mock('LaravelBook\Ardent\Test\ValidatingModel[validate]');

        $model->shouldReceive('validate')
            ->andReturn(true);

        $model->save();

        $this->assertEquals(1, $model->saveCalled);
    }

    public function testValidationUsesPassedRules()
    {
        Validator::clearResolvedInstances();

        $model = new ValidatingModel;

        $rules = array('hello' => uniqid());

        Input::shouldReceive('hasSessionStore');

        Validator::shouldReceive('make')->once()
            ->with(m::any(), $rules, m::any())
            ->andReturn($this->validator);

        $model->validate($rules);
    }

    public function testValidationUsesStaticRules()
    {
        Validator::clearResolvedInstances();

        $model = new ValidatingModel;

        $rules = array('hello' => uniqid());

        Input::shouldReceive('hasSessionStore');

        Validator::shouldReceive('make')->once()
            ->with(m::any(), ValidatingModel::$rules, m::any())
            ->andReturn($this->validator);

        $model->validate();
    }

    public function testErrorsAreAlwaysAvailable()
    {
        $model = new ValidatingModel;

        $this->assertInstanceOf('Illuminate\Support\MessageBag', $model->errors());
    }

    public function testValidationProvidesErrors()
    {
        $model = new ValidatingModel;
        $messages = new MessageBag;


        $this->validator->shouldReceive('messages')
            ->andReturn($messages);

        $model->validate();

        $this->assertSame($messages, $model->errors());
        $this->assertSame($messages, $model->validationErrors);
    }

    public function testValidationOverridesOldErrors()
    {
        $model = new ValidatingModel;
        $messages = new MessageBag;
        $model->validationErrors = $messages;

        $messages->add('hello', 'world');

        $this->validator->shouldReceive('passes')->once()
            ->andReturn(true);

        $model->validate();

        $this->assertInstanceOf('Illuminate\Support\MessageBag', $model->errors());
        $this->assertNotSame($messages, $model->errors());
        $this->assertCount(0, $model->errors());
    }

    public function testValidationFailureFlashesInputData()
    {
        // Reset Input mock
        Input::clearResolvedInstances();

        $model = new ValidatingModel;

        $this->validator->shouldReceive('passes')
            ->andReturn(false);

        Validator::shouldReceive('make')
            ->andReturn($this->validator);

        Input::shouldReceive('hasSessionStore')
            ->andReturn(true);

        Input::shouldReceive('flash')->once();

        $model->validate();
    }
}

class ValidatingModel extends Ardent
{
    public static $rules = array(
        'name' => array('required'),
        'email' => array('email')
    );

    public $saveCalled = 0;

    protected function performSave(array $options) {
        $this->saveCalled++;
    }
}
