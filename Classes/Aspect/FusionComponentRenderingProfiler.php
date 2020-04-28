<?php

namespace FormatD\FusionAnalyzer\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\ObjectAccess;
use Psr\Log\LoggerInterface;

/**
 * An aspect which logs rendering times of fusion components
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class FusionComponentRenderingProfiler
{

	/**
	 * @Flow\Inject
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var int
	 */
	protected $startNestingLevel = 1;

	/**
	 * @var int
	 */
	protected $logEnable = false;

	/**
	 * @Flow\InjectConfiguration(type="Settings", package="FormatD.FusionAnalyzer.triggerComponentName")
	 * @var string
	 */
	protected $triggerComponentName;

    /**
	 * @Flow\Around("setting(FormatD.FusionAnalyzer.enabled) && within(Neos\Fusion\FusionObjects\AbstractFusionObject) && method(.*->evaluate())")
     * @param JoinPointInterface $joinPoint The current joinpoint
     * @return mixed
     */
    public function analyzeEvaluateFunction(JoinPointInterface $joinPoint)
    {
		$fusionObjectName = ObjectAccess::getProperty($joinPoint->getProxy(), 'fusionObjectName', true);
		$path = ObjectAccess::getProperty($joinPoint->getProxy(), 'path', true);
		$nestingLevel = $this->getNestingLevel($path);

		$enabledInCurrentRecursion = false;
		//$this->log($nestingLevel, '--- ' . $fusionObjectName);

		if ($fusionObjectName === $this->triggerComponentName && $this->logEnable === false) {
			$this->logEnable = true;
			$this->startNestingLevel = $nestingLevel;
			$enabledInCurrentRecursion = true;
		}

		if (!$this->logEnable) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		$time = microtime(true);

		$this->log($nestingLevel, 'Start evaluating: ' . $fusionObjectName);

		$response = $joinPoint->getAdviceChain()->proceed($joinPoint);

		$executionTime = (microtime(true) - $time);

		$this->log($nestingLevel, $executionTime . 's evaluation time: (' . $fusionObjectName . ')');

		if ($enabledInCurrentRecursion) {
			$this->logEnable = false;
		}

		return $response;
    }

	/**
	 * @Flow\Around("within(Neos\Fusion\FusionObjects\AbstractFusionObject) && method(.*->render())")
	 * @param JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed
	 */
	public function analyzeRenderFunction(JoinPointInterface $joinPoint)
	{
		if (!$this->logEnable) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		$fusionObjectName = ObjectAccess::getProperty($joinPoint->getProxy(), 'fusionObjectName', true);
		$path = ObjectAccess::getProperty($joinPoint->getProxy(), 'path', true);
		$nestingLevel = $this->getNestingLevel($path);

		$time = microtime(true);

		$response = $joinPoint->getAdviceChain()->proceed($joinPoint);

		$executionTime = (microtime(true) - $time);

		$this->log($nestingLevel, $executionTime . 's rendering time (' . $fusionObjectName . ')');

		return $response;
	}

	/**
	 * @param string $path
	 */
	protected function getNestingLevel($path) {
		return substr_count($path, '/');
	}

	/**
	 * @param string $path
	 */
	protected function getRelativeNestingLevel($nestingLevel) {
		return $nestingLevel - $this->startNestingLevel;
	}

	/**
	 * @param int $nestingLevel
	 * @param string $string
	 */
	protected function log($nestingLevel, $string) {
		$relativeNestingLevel = $this->getRelativeNestingLevel($nestingLevel);
		if ($relativeNestingLevel > 5) {
			return;
		}
		$this->logger->info(str_repeat(' ' , $relativeNestingLevel) . $string);
	}

}
