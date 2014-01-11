<?php namespace LaravelBook\Ardent\Test;

use PHPUnit_Framework_TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Mockery as m;

class TestCase extends PHPUnit_Framework_TestCase
{
    public function setup()
    {
        $this->validator = m::mock('Illuminate\Validation\Validator')
            ->shouldIgnoreMissing();

        Validator::shouldReceive('make')
            ->andReturn($this->validator);

        Input::shouldReceive('hasSessionStore')
            ->andReturn(false);
    }
    
    public function teardown()
    {
        Input::clearResolvedInstances();
        Validator::clearResolvedInstances();

        m::close();
        parent::teardown();
    }
}
