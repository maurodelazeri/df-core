<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Scripting\ScriptEngineManager;
use \Log;

/**
 * Script
 * Scripting as a Service
 */
class Script extends BaseRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string $content Content of the script
     */
    protected $content;
    /**
     * @var array $engineConfig Configuration for the scripting engine used by the script
     */
    protected $engineConfig;
    /**
     * @var array $scriptConfig Configuration for the engine for this particular script
     */
    protected $scriptConfig;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new Script Service
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        $config = ArrayUtils::clean(ArrayUtils::get($settings, 'config'));
        Session::replaceLookups( $config, true );

        if (null === ($this->content = ArrayUtils::get($config, 'content', null, true))) {
            throw new \InvalidArgumentException('Script content can not be empty.');
        }

        if (null === ($this->engineConfig = ArrayUtils::get($config, 'engine', null, true))) {
            throw new \InvalidArgumentException('Script engine configuration can not be empty.');
        }

        $this->scriptConfig = ArrayUtils::clean(ArrayUtils::get($config, 'config', [], true));
    }

    /**
     * @return mixed
     */
    protected function handleGET()
    {
        return $this->runScript();
    }

    /**
     * @return mixed
     */
    protected function handlePOST()
    {
        return $this->runScript();
    }

    /**
     * @return mixed
     * @throws InternalServerErrorException
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     */
    protected function runScript()
    {
        $logOutput = $this->request->getParameterAsBool('log_output', true);
        $data = ['request' => $this->request->toArray()];
        $output = null;
        $result = ScriptEngineManager::runScript(
            $this->content,
            'script.' . $this->name,
            $this->engineConfig,
            $this->scriptConfig,
            $data,
            $output
        );

        if (!empty($output) && $logOutput) {
            \Log::info("Script '{$this->name}' output:" . PHP_EOL . $output . PHP_EOL);
        }

        //  The script runner should return an array
        if (is_array($result) && isset($result['__tag__'])) {
            $scriptResult = ArrayUtils::get($result, 'script_result', []);
            //  Bail on errors...
            if (is_array($scriptResult) && (isset($scriptResult['error']) || isset($scriptResult['exception']))) {
                throw new InternalServerErrorException(ArrayUtils::get($scriptResult, 'exception',
                    ArrayUtils::get($scriptResult, 'error')));
            }

            return $scriptResult;
        } else {
            Log::error('  * Script did not return an array: ' . print_r($result, true));
        }

        return $output;
    }

    // override base class for API Docs, get from custom config
    public function getApiDocModels()
    {
        return [];
    }

    public function getApiDocInfo()
    {
        return [
            'resourcePath' => '/' . $this->name,
            'produces'     => ['application/json', 'application/xml'],
            'consumes'     => ['application/json', 'application/xml'],
            'apis'         => [],
            'models'       => [],
        ];
    }
}
