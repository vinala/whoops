<?php
/**
* Whoops - php errors for cool kids
* @author Filipe Dobreira <http://github.com/filp>
* Plaintext handler for command line and logs.
* @author Pierre-Yves Landuré <https://howto.biapy.com/>
*/

namespace Whoops\Handler;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Whoops\Exception\Frame;
use Vinala\Kernel\MVC\View;

/**
* Handler outputing plaintext error messages. Can be used
* directly, or will be instantiated automagically by Whoops\Run
* if passed to Run::pushHandler
*/
class PlainTextHandler extends Handler
{
    const VAR_DUMP_PREFIX = '   | ';

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    public $msg;
    public $bg_color;

    /**
     * @var bool
     */
    private $addTraceToOutput = true;

    /**
     * @var bool|integer
     */
    private $addTraceFunctionArgsToOutput = false;

    /**
     * @var integer
     */
    private $traceFunctionArgsOutputLimit = 1024;

    /**
     * @var bool
     */
    private $onlyForCommandLine = false;

    /**
     * @var bool
     */
    private $outputOnlyIfCommandLine = true;

    /**
     * @var bool
     */
    private $loggerOnly = false;

    /**
     * Constructor.
     * @throws InvalidArgumentException     If argument is not null or a LoggerInterface
     * @param  Psr\Log\LoggerInterface|null $logger
     */
    public function __construct($logger = null)
    {
        $this->setLogger($logger);
    }

    /**
     * Set the output logger interface.
     * @throws InvalidArgumentException     If argument is not null or a LoggerInterface
     * @param  Psr\Log\LoggerInterface|null $logger
     */
    public function setLogger($logger = null)
    {
        if (! (is_null($logger)
            || $logger instanceof LoggerInterface)) {
            throw new InvalidArgumentException(
                'Argument to ' . __METHOD__ .
                " must be a valid Logger Interface (aka. Monolog), " .
                get_class($logger) . ' given.'
            );
        }

        $this->logger = $logger;
    }

    /**
     * @return Psr\Log\LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Add error trace to output.
     * @param  bool|null  $addTraceToOutput
     * @return bool|$this
     */
    public function addTraceToOutput($addTraceToOutput = null)
    {
        if (func_num_args() == 0) {
            return $this->addTraceToOutput;
        }

        $this->addTraceToOutput = (bool) $addTraceToOutput;
        return $this;
    }

    /**
     * Add error trace function arguments to output.
     * Set to True for all frame args, or integer for the n first frame args.
     * @param  bool|integer|null $addTraceFunctionArgsToOutput
     * @return null|bool|integer
     */
    public function addTraceFunctionArgsToOutput($addTraceFunctionArgsToOutput = null)
    {
        if (func_num_args() == 0) {
            return $this->addTraceFunctionArgsToOutput;
        }

        if (! is_integer($addTraceFunctionArgsToOutput)) {
            $this->addTraceFunctionArgsToOutput = (bool) $addTraceFunctionArgsToOutput;
        } else {
            $this->addTraceFunctionArgsToOutput = $addTraceFunctionArgsToOutput;
        }
    }

    /**
     * Set the size limit in bytes of frame arguments var_dump output.
     * If the limit is reached, the var_dump output is discarded.
     * Prevent memory limit errors.
     * @var integer
     */
    public function setTraceFunctionArgsOutputLimit($traceFunctionArgsOutputLimit)
    {
        $this->traceFunctionArgsOutputLimit = (integer) $traceFunctionArgsOutputLimit;
    }

    /**
     * Get the size limit in bytes of frame arguments var_dump output.
     * If the limit is reached, the var_dump output is discarded.
     * Prevent memory limit errors.
     * @return integer
     */
    public function getTraceFunctionArgsOutputLimit()
    {
        return $this->traceFunctionArgsOutputLimit;
    }

    /**
     * Restrict error handling to command line calls.
     * @param  bool|null $onlyForCommandLine
     * @return null|bool
     */
    public function onlyForCommandLine($onlyForCommandLine = null)
    {
        if (func_num_args() == 0) {
            return $this->onlyForCommandLine;
        }
        $this->onlyForCommandLine = (bool) $onlyForCommandLine;
    }

    /**
     * Output the error message only if using command line.
     * else, output to logger if available.
     * Allow to safely add this handler to web pages.
     * @param  bool|null $outputOnlyIfCommandLine
     * @return null|bool
     */
    public function outputOnlyIfCommandLine($outputOnlyIfCommandLine = null)
    {
        if (func_num_args() == 0) {
            return $this->outputOnlyIfCommandLine;
        }
        $this->outputOnlyIfCommandLine = (bool) $outputOnlyIfCommandLine;
    }

    /**
     * Only output to logger.
     * @param  bool|null $loggerOnly
     * @return null|bool
     */
    public function loggerOnly($loggerOnly = null)
    {
        if (func_num_args() == 0) {
            return $this->loggerOnly;
        }

        $this->loggerOnly = (bool) $loggerOnly;
    }

    /**
     * Check, if possible, that this execution was triggered by a command line.
     * @return bool
     */
    private function isCommandLine()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * Test if handler can process the exception..
     * @return bool
     */
    private function canProcess()
    {
        return $this->isCommandLine() || !$this->onlyForCommandLine();
    }

    /**
     * Test if handler can output to stdout.
     * @return bool
     */
    private function canOutput()
    {
        return ($this->isCommandLine() || ! $this->outputOnlyIfCommandLine())
            && ! $this->loggerOnly();
    }

    /**
     * Get the frame args var_dump.
     * @param  \Whoops\Exception\Frame $frame [description]
     * @param  integer                 $line  [description]
     * @return string
     */
    private function getFrameArgsOutput(Frame $frame, $line)
    {
        if ($this->addTraceFunctionArgsToOutput() === false
            || $this->addTraceFunctionArgsToOutput() < $line) {
            return '';
        }

        // Dump the arguments:
        ob_start();
        var_dump($frame->getArgs());
        if (ob_get_length() > $this->getTraceFunctionArgsOutputLimit()) {
            // The argument var_dump is to big.
            // Discarded to limit memory usage.
            ob_clean();
            return sprintf(
                "\n%sArguments dump length greater than %d Bytes. Discarded.",
                self::VAR_DUMP_PREFIX,
                $this->getTraceFunctionArgsOutputLimit()
            );
        }

        return sprintf("\n%s",
            preg_replace('/^/m', self::VAR_DUMP_PREFIX, ob_get_clean())
        );
    }

    /**
     * Get the exception trace as plain text.
     * @return string
     */
    private function getTraceOutput()
    {
        if (! $this->addTraceToOutput()) {
            return '';
        }
        $inspector = $this->getInspector();
        $frames = $inspector->getFrames();

        $response = "\nStack trace:";

        $line = 1;
        foreach ($frames as $frame) {
            /** @var Frame $frame */
            $class = $frame->getClass();

            $template = "\n%3d. %s->%s() %s:%d%s";
            if (! $class) {
                // Remove method arrow (->) from output.
                $template = "\n%3d. %s%s() %s:%d%s";
            }

            $response .= sprintf(
                $template,
                $line,
                $class,
                $frame->getFunction(),
                $frame->getFile(),
                $frame->getLine(),
                $this->getFrameArgsOutput($frame, $line)
            );

            $line++;
        }

        return $response;
    }

    /**
     * @return int
     */
    public function handle()
    {
        if (! $this->canProcess()) {
            return Handler::DONE;
        }

        $exception = $this->getException();

        if ($this->getLogger()) {
            $this->getLogger()->error($response);
        }

        if(! is_null ($exception)) 
            {

                $showed = false;

                $this->root();

                if(isset($exception->view))
                {
                    if(View::exists($exception->view))
                    {
                        view($exception->view)->show();    
                        $showed = true;
                    }
                }

                $nest = __DIR__.'/../../../../../../';

                if(! $showed)
                {
                    if(View::exists(config('error.regular') , $nest))
                    {
                        view(config('error.regular') , [] , $nest)->show();
                        $showed = true;
                    }
                }

                if(! $showed)
                {
                    $msg=$this->msg;
                    $bg_color=$this->bg_color;
                    ?>

                            <head>
                                <meta charset="utf-8"/>
                                <title>Ohlala! It seems that something went wrong</title>
                                <style type="text/css">
                                    @font-face {
                                      font-family: 'segoe';
                                      src: url('app/resources/fonts/segoeuil.ttf') format('truetype');
                                    }

                                    body
                                    {
                                        background: #26324f;
                                        margin: 0px;
                                        padding: 0px;
                                    }

                                    div 
                                    {
                                        box-shadow: 0px 3px 6px 3px rgba(0,0,0,0.2);
                                        border:1px solid gray;
                                        border-radius:5px;
                                        display: inline-block;
                                        padding:30px;
                                        font-size: 16px;
                                        /*font: 20px Georgia, "segoe", Times, serif;*/
                                        font: 20px "segoe";
                                        width: 460px;
                                        margin: 60px auto;
                                        display: block;
                                        background: white;
                                        text-align: center;
                                    }
                                </style>
                            </head>
                            <body>

                                <div>
                                    Ohlala! It seems that something went wrong
                                </div>
                                
                            </body>

                    <?php
                }
            }
        return Handler::QUIT;
        if (! $this->canOutput()) {
            return Handler::DONE;
        }
        if (class_exists('\Whoops\Util\Misc')
            && \Whoops\Util\Misc::canSendHeaders()) {
            //header('Content-Type: html/text');
        }
        //if(! is_null ($exception)) echo "fff";

        return Handler::QUIT;
    }


    /**
    * Set the app root
    *
    * @param string $url
    * @return string
    */
    protected function root()
    {
        if(isset($_GET['_framework_url_']))
        {
            $url = $_GET['_framework_url_'];
        }
        else return false;

        $parts = explode('/', $url);
        $count = count($parts)-2;
        //
        $path = $parts > 1 ? '../' : '';

        for ($i=0; $i <$count; $i++) 
        { 
            $path .= '../';
        }

        \Vinala\Kernel\Foundation\Application::$path = $path;
        echo($path);
        return $path;
    }
    
}
