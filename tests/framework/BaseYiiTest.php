<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\tests\framework;

use Psr\Log\LogLevel;
use yii\helpers\Yii;
use yii\exceptions\InvalidArgumentException;
use yii\helpers\BaseYii;
use yii\di\Container;
use yii\log\Logger;
use yii\profile\Profiler;
use yii\tests\data\base\Singer;
use yii\tests\TestCase;

/**
 * BaseYiiTest.
 * @group base
 */
class BaseYiiTest extends TestCase
{
    public $aliases;

    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
        //$this->aliases = Yii::$aliases;
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->destroyApplication();
        //Yii::$aliases = $this->aliases;
    }

    public function testAlias()
    {
        /// TODO fix YII_PATH definition
        /// $this->assertEquals(YII_PATH, $this->app->getAlias('@yii'));

        $this->assertFalse($this->app->getAlias('@nonexisting', false));

        $aliasNotBeginsWithAt = 'alias not begins with @';
        $this->assertEquals($aliasNotBeginsWithAt, $this->app->getAlias($aliasNotBeginsWithAt));

        $this->app->setAlias('@yii', '/yii/framework');
        $this->assertEquals('/yii/framework', $this->app->getAlias('@yii'));
        $this->assertEquals('/yii/framework/test/file', $this->app->getAlias('@yii/test/file'));
        $this->app->setAlias('yii/gii', '/yii/gii');
        $this->assertEquals('/yii/framework', $this->app->getAlias('@yii'));
        $this->assertEquals('/yii/framework/test/file', $this->app->getAlias('@yii/test/file'));
        $this->assertEquals('/yii/gii', $this->app->getAlias('@yii/gii'));
        $this->assertEquals('/yii/gii/file', $this->app->getAlias('@yii/gii/file'));

        $this->app->setAlias('@tii', '@yii/test');
        $this->assertEquals('/yii/framework/test', $this->app->getAlias('@tii'));

        $this->app->setAlias('@yii', null);
        $this->assertFalse($this->app->getAlias('@yii', false));
        $this->assertEquals('/yii/gii/file', $this->app->getAlias('@yii/gii/file'));

        $this->app->setAlias('@some/alias', '/www');
        $this->assertEquals('/www', $this->app->getAlias('@some/alias'));

        $erroneousAlias = '@alias_not_exists';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid path alias: %s', $erroneousAlias));
        $this->app->getAlias($erroneousAlias, true);
    }

    public function testGetRootAlias()
    {
        $this->app->setAlias('@yii', '/yii/framework');
        $this->assertEquals('@yii', $this->app->getRootAlias('@yii'));
        $this->assertEquals('@yii', $this->app->getRootAlias('@yii/test/file'));
        $this->app->setAlias('@yii/gii', '/yii/gii');
        $this->assertEquals('@yii/gii', $this->app->getRootAlias('@yii/gii'));
    }

    /*
     * Phpunit calculate coverage better in case of small tests
     */
    public function testSetAlias()
    {
        $this->app->setAlias('@yii/gii', '/yii/gii');
        $this->assertEquals('/yii/gii', $this->app->getAlias('@yii/gii'));
        $this->app->setAlias('@yii/tii', '/yii/tii');
        $this->assertEquals('/yii/tii', $this->app->getAlias('@yii/tii'));
    }

    public function testGetVersion()
    {
        $this->assertTrue((bool) preg_match('~\d+\.\d+(?:\.\d+)?(?:-\w+)?~', $this->app->getVersion()));
    }

    public function testCreateObject()
    {
        $object = $this->app->createObject([
            '__class' => Singer::class,
            'firstName' => 'John',
        ]);
        $this->assertTrue($object instanceof Singer);
        $this->assertSame('John', $object->firstName);

        $object = $this->app->createObject([
            '__class' => Singer::class,
            'firstName' => 'Michael',
        ]);
        $this->assertTrue($object instanceof Singer);
        $this->assertSame('Michael', $object->firstName);

        $this->expectException(\yii\di\exceptions\InvalidConfigException::class);
        $this->expectExceptionMessage('Object configuration array must contain a "__class" element.');
        $object = $this->app->createObject([
            'firstName' => 'John',
        ]);
    }

    /**
     * @depends testCreateObject
     */
    public function testCreateObjectCallable()
    {
        // Test passing in of normal params combined with DI params.
        $this->assertNotEmpty($this->app->createObject(function (Singer $singer, $a) {
            return $a === 'a';
        }, ['a']));


        $singer = new Singer();
        $singer->firstName = 'Bob';
        $this->assertNotEmpty($this->app->createObject(function (Singer $singer, $a) {
            return $singer->firstName === 'Bob';
        }, [$singer, 'a']));


        $this->assertNotEmpty($this->app->createObject(function (Singer $singer, $a = 3) {
            return true;
        }));
    }

    public function testCreateObjectEmptyArrayException()
    {
        $this->expectException(\yii\di\exceptions\InvalidConfigException::class);
        $this->expectExceptionMessage('Object configuration array must contain a "__class" element.');

        $this->app->createObject([]);
    }

    public function testCreateObjectInvalidConfigException()
    {
        $this->expectException(\yii\di\exceptions\InvalidConfigException::class);
        $this->expectExceptionMessage('Unsupported configuration type: ' . gettype(null));

        $this->app->createObject(null);
    }

    /**
     * @covers \yii\BaseYii::info()
     * @covers \yii\BaseYii::warning()
     * @covers \yii\BaseYii::debug()
     * @covers \yii\BaseYii::error()
     */
    public function testLog()
    {
        $logger = $this->getMockBuilder(Logger::class)
            ->setMethods(['log'])
            ->getMock();
        $this->container->set('logger', $logger);

        $logger->expects($this->exactly(4))
            ->method('log')
            ->withConsecutive(
                [
                    $this->equalTo(LogLevel::INFO),
                    $this->equalTo('info message'),
                    $this->equalTo(['category' => 'info category'])
                ],
                [
                    $this->equalTo(LogLevel::WARNING),
                    $this->equalTo('warning message'),
                    $this->equalTo(['category' => 'warning category']),
                ],
                [
                    $this->equalTo(LogLevel::DEBUG),
                    $this->equalTo('trace message'),
                    $this->equalTo(['category' => 'trace category'])
                ],
                [
                    $this->equalTo(LogLevel::ERROR),
                    $this->equalTo('error message'),
                    $this->equalTo(['category' => 'error category'])
                ]
            );

        BaseYii::info('info message', 'info category');
        BaseYii::warning('warning message', 'warning category');
        BaseYii::debug('trace message', 'trace category');
        BaseYii::error('error message', 'error category');

    }

    /*
     * Phpunit calculate coverage better in case of small tests
     */
    public function testLoggerWithException()
    {
        $logger = $this->getMockBuilder(Logger::class)
            ->setMethods(['log'])
            ->getMock();
        $this->container->set('logger', $logger);
        $throwable = new \Exception('test');

        $logger
            ->expects($this->once())
            ->method('log')->with(
                $this->equalTo(LogLevel::ERROR),
                $this->equalTo($throwable),
                $this->equalTo(['category' => 'error category'])
            );

        BaseYii::error($throwable, 'error category');
    }

    /**
     * @covers \yii\BaseYii::beginProfile()
     * @covers \yii\BaseYii::endProfile()
     */
    public function testProfile()
    {
        $profiler = $this->getMockBuilder('yii\profile\Profiler')
            ->setMethods(['begin', 'end'])
            ->getMock();
        $this->container->set('profiler', $profiler);

        $profiler->expects($this->exactly(2))
            ->method('begin')
            ->withConsecutive(
                [
                    $this->equalTo('Profile message 1'),
                    $this->equalTo(['category' => 'Profile category 1'])
                ],
                [
                    $this->equalTo('Profile message 2'),
                    $this->equalTo(['category' => 'Profile category 2']),
                ]
            );

        $profiler->expects($this->exactly(2))
            ->method('end')
            ->withConsecutive(
                [
                    $this->equalTo('Profile message 1'),
                    $this->equalTo(['category' => 'Profile category 1'])
                ],
                [
                    $this->equalTo('Profile message 2'),
                    $this->equalTo(['category' => 'Profile category 2']),
                ]
            );

        BaseYii::beginProfile('Profile message 1', 'Profile category 1');
        BaseYii::endProfile('Profile message 1', 'Profile category 1');
        BaseYii::beginProfile('Profile message 2', 'Profile category 2');
        BaseYii::endProfile('Profile message 2', 'Profile category 2');
    }
}
