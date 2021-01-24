<?php
  namespace Seekr;

  use Seekr\TestResult;
  use Seekr\Timer;

  /**
   *  A simple test library developed for writing better tests on PHP ecosystem
   *  Seekr provides a testable interface for a class
   */
  abstract class Seekr
  {
    protected $testClassName = "";
    protected $testLog = array();

    protected $_successCount = 0;
    protected $_failureCount = 0;

    public function getSuccessCount()
    {
      return $this->_successCount;
    }

    public function getFailureCount()
    {
      return $this->_failureCount;
    }

    /**
     * HOOKS
     * -------------------------
     * Seekr provides some lifecycle hooks that you can use to catch up with specific moments, 
     * perform actions that you may want then
     */
    
    /**
     * This hook is called before starting to run tests in this test class
     *
     * @return void
     */
    public function setUp() {}

    /**
     * This hook is called after all tests in this test class have run
     *
     * @return void
     */
    public function finish() {}

    /**
     * This hook is called before each test of this test class is run
     *
     * @return void
     */
    public function mountedTest() {}

    /**
     * This hook is called after each test of this test class is run
     *
     * @return void
     */
    public function unmountedTest() {}

    /** LOGIC */

    /**
     * Logs the result of a test. 
     * Keeps track of results for later inspection. 
     * Overridable to log elsewhere.
     */
    protected function logTest(TestResult $result)
    {
      $this->testLog[] = $result;
    }

    /**
     * Serializes a test result. 
     * Overridable to do something else as serialization.
     */
    public function serializeTestResult(TestResult $result)
    {
      $exceptionOutput = "";

      if (!$result->isSuccess()) {
        $resultException = $result->getException();
        
        if ($resultException instanceof Contradiction) {
          $exceptionMessage = $resultException->toString();
        } else {
          $exceptionMessage = sprintf( "\nException : %s", $resultException->getMessage() );
        }

        $testOutput = empty($result->getOutput())
          ? ""
          : sprintf( "\nOutput : \n%s", $result->getOutput());

        $exceptionMetadata = sprintf( "\n(Lines: %d-%d ~ File: %s)\n"
            ,$result->getTest()->getStartLine()
            ,$result->getTest()->getEndLine()
            ,$result->getTest()->getFileName()
        );

        $exceptionOutput = sprintf("%s%s%s"
                            ,$exceptionMetadata
                            ,$exceptionMessage
                            ,$testOutput
                          );
      }

      # returns the error log
      return sprintf( "%s.%s() was a %s ~ in %.9f seconds %s"
        ,$this->testClassName
        ,$result->getName()
        ,$result->isSuccess() ? 'SUCCESS' : 'FAILURE'
        ,$result->getExecutionTime() # formats test execution time into a string
        ,$exceptionOutput
        );
    }

    /**
     * Prints a message.
     *
     * @param string $contents
     * @return void
     */
    public function consoleLog(string $contents) {
      printf("\n\033[1mSeekr >\033[0m %s", $contents);
    }

    /**
     * Serializes a test result. Overridable to do something else as serialization.
     */
    public function outputTestLog()
    {
      foreach ($this->testLog as $testResult) {
        $this->consoleLog($this->serializeTestResult($testResult));
      }
    }

    /**
     * Runs tests in the child test class
     *
     * @return void
     */
    public final function runTests()
    {
      # test execution timer
      $timer = new Timer(true);

      # create a reflection class
      $reflectionClass = new \ReflectionClass( $this );
      $this->testClassName = $reflectionClass->getName();

      # RUN_HOOK setUp()
      $this->setUp();

      $methodsList = $reflectionClass->getMethods();

      # run every test
      foreach($methodsList as $method)
      {
        $methodname = $method->getName();

        # RUN_HOOK mountedTest()
        $this->mountedTest();
        
        if ( strlen( $methodname ) > 4 && substr( $methodname, 0, 4 ) == 'test' ) {
          # condition above means this is a test method, so mount it!
          
          ob_start();

          # started output buffering
          try {
            $timer->start(); # start timer
            $this->$methodname(); # run test method
            $result = TestResult::createSuccess( $this, $method );
            ++$this->_successCount;
          } catch( \Exception $ex ) {
            $result = TestResult::createFailure( $this, $method, $ex );
            ++$this->_failureCount;
          }
          
          $timer->stop(); # stop timer and set execution time
          $result->setExecutionTime( $timer->passedTime() );

          $output = ob_get_clean();
          $result->setOutput( $output );
          
          $this->logTest( $result );

          # RUN_HOOK unmountedTest()
          $this->unmountedTest();
        }
      }

      # RUN_HOOK finish()
      $this->finish();
    }

    /**
     * Outputs the test results. Overridable to output to elsewhere
     *
     * @return void
     */
    public function seeTestResults()
    {
      $this->outputTestLog();
      $this->logSummary();
    }

    /**
     * Prints a summary from the current test results
     *
     * @return void
     */
    public final function logSummary()
    {
      $this->consoleLog( 
        sprintf( "SUMMARY %s : %d Success %d Failed\n"
          ,$this->testClassName
          ,$this->_successCount
          ,$this->_failureCount
        ) 
      );

    }
  }