<?php

namespace DominionEnterprises\Mongo;

/**
 * @coversDefaultClass \DominionEnterprises\Mongo\Queue
 * @covers ::<private>
 */
final class QueueTest extends \PHPUnit_Framework_TestCase
{
    private $_collection;
    private $_queue;

    public function setUp()
    {
        $mongo = new \MongoClient('mongodb://localhost');
        $this->_collection = $mongo->selectDB('testing')->selectCollection('messages');
        $this->_collection->drop();

        $this->_queue = new Queue('mongodb://localhost', 'testing', 'messages');
    }

    /**
     * @test
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     */
    public function constructWithNonStringUrl()
    {
        new Queue(1, 'testing', 'messages');
    }

    /**
     * @test
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     */
    public function constructWithNonStringDb()
    {
        new Queue('mongodb://localhost', true, 'messages');
    }

    /**
     * @test
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     */
    public function constructWithNonStringCollection()
    {
        new Queue('mongodb://localhost', 'testing', new \stdClass());
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     */
    public function ensureGetIndex()
    {
        $this->_queue->ensureGetIndex(array('type' => 1), array('boo' => -1));
        $this->_queue->ensureGetIndex(array('another.sub' => 1));

        $this->assertSame(4, count($this->_collection->getIndexInfo()));

        $expectedOne = array('running' => 1, 'payload.type' => 1, 'priority' => 1, 'created' => 1, 'payload.boo' => -1, 'earliestGet' => 1);
        $resultOne = $this->_collection->getIndexInfo();
        $this->assertSame($expectedOne, $resultOne[1]['key']);

        $expectedTwo = array('running' => 1, 'resetTimestamp' => 1);
        $resultTwo = $this->_collection->getIndexInfo();
        $this->assertSame($expectedTwo, $resultTwo[2]['key']);

        $expectedThree = array('running' => 1, 'payload.another.sub' => 1, 'priority' => 1, 'created' => 1, 'earliestGet' => 1);
        $resultThree = $this->_collection->getIndexInfo();
        $this->assertSame($expectedThree, $resultThree[3]['key']);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \Exception
     */
    public function ensureGetIndexWithTooLongCollectionName()
    {
        $collectionName = 'messages012345678901234567890123456789012345678901234567890123456789';
        $collectionName .= '012345678901234567890123456789012345678901234567890123456789';//128 chars

        $queue = new Queue('mongodb://localhost', 'testing', $collectionName);
        $queue->ensureGetIndex(array());
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithNonStringBeforeSortKey()
    {
        $this->_queue->ensureGetIndex(array(0 => 1));
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithNonStringAfterSortKey()
    {
        $this->_queue->ensureGetIndex(array('field' => 1), array(0 => 1));
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithBadBeforeSortValue()
    {
        $this->_queue->ensureGetIndex(array('field' => 'NotAnInt'));
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithBadAfterSortValue()
    {
        $this->_queue->ensureGetIndex(array(), array('field' => 'NotAnInt'));
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     */
    public function ensureCountIndex()
    {
        $this->_queue->ensureCountIndex(array('type' => 1, 'boo' => -1), false);
        $this->_queue->ensureCountIndex(array('another.sub' => 1), true);

        $this->assertSame(3, count($this->_collection->getIndexInfo()));

        $expectedOne = array('payload.type' => 1, 'payload.boo' => -1);
        $resultOne = $this->_collection->getIndexInfo();
        $this->assertSame($expectedOne, $resultOne[1]['key']);

        $expectedTwo = array('running' => 1, 'payload.another.sub' => 1);
        $resultTwo = $this->_collection->getIndexInfo();
        $this->assertSame($expectedTwo, $resultTwo[2]['key']);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     */
    public function ensureCountIndexWithPrefixOfPrevious()
    {
        $this->_queue->ensureCountIndex(array('type' => 1, 'boo' => -1), false);
        $this->_queue->ensureCountIndex(array('type' => 1), false);

        $this->assertSame(2, count($this->_collection->getIndexInfo()));

        $expected = array('payload.type' => 1, 'payload.boo' => -1);
        $result = $this->_collection->getIndexInfo();
        $this->assertSame($expected, $result[1]['key']);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureCountIndexWithNonStringKey()
    {
        $this->_queue->ensureCountIndex(array(0 => 1), false);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureCountIndexWithBadValue()
    {
        $this->_queue->ensureCountIndex(array('field' => 'NotAnInt'), false);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureCountIndexWithNonBoolIncludeRunning()
    {
        $this->_queue->ensureCountIndex(array('field' => 1), 1);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getByBadQuery()
    {
        $this->_queue->send(array('key1' => 0, 'key2' => true));

        $result = $this->_queue->get(array('key3' => 0), PHP_INT_MAX, 0);
        $this->assertNull($result);

        $this->assertSame(1, $this->_collection->count());
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonIntWaitDuration()
    {
        $this->_queue->get(array(), 0, 'NotAnInt');
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonIntPollDuration()
    {
        $this->_queue->get(array(), 0, 0, new \stdClass());
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithNegativePollDuration()
    {
        $this->_queue->send(array('key1' => 0));
        $this->assertNotNull($this->_queue->get(array(), 0, 0, -1));
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonStringKey()
    {
        $this->_queue->get(array(0 => 'a value'), 0);
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonIntRunningResetDuration()
    {
        $this->_queue->get(array(), true);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getByFullQuery()
    {
        $messageOne = array('id' => 'SHOULD BE REMOVED', 'key1' => 0, 'key2' => true);

        $this->_queue->send($messageOne);
        $this->_queue->send(array('key' => 'value'));

        $result = $this->_queue->get($messageOne, PHP_INT_MAX, 0);

        $this->assertNotSame($messageOne['id'], $result['id']);

        $messageOne['id'] = $result['id'];
        $this->assertSame($messageOne, $result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBySubDocQuery()
    {
        $messageTwo = array(
            'one' => array('two' => array('three' => 5, 'notused' => 'notused'), 'notused' => 'notused'),
            'notused' => 'notused',
        );

        $this->_queue->send(array('key1' => 0, 'key2' => true));
        $this->_queue->send($messageTwo);

        $result = $this->_queue->get(array('one.two.three' => array('$gt' => 4)), PHP_INT_MAX, 0);
        $this->assertSame(array('id' => $result['id']) + $messageTwo, $result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBeforeAck()
    {
        $messageOne = array('key1' => 0, 'key2' => true);

        $this->_queue->send($messageOne);
        $this->_queue->send(array('key' => 'value'));

        $this->_queue->get($messageOne, PHP_INT_MAX, 0);

        //try get message we already have before ack
        $result = $this->_queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertNull($result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithCustomPriority()
    {
        $messageOne = array('key' => 0);
        $messageTwo = array('key' => 1);
        $messageThree = array('key' => 2);

        $this->_queue->send($messageOne, 0, 0.5);
        $this->_queue->send($messageTwo, 0, 0.4);
        $this->_queue->send($messageThree, 0, 0.3);

        $resultOne = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultTwo = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultThree = $this->_queue->get(array(), PHP_INT_MAX, 0);

        $this->assertSame(array('id' => $resultOne['id']) + $messageThree, $resultOne);
        $this->assertSame(array('id' => $resultTwo['id']) + $messageTwo, $resultTwo);
        $this->assertSame(array('id' => $resultThree['id']) + $messageOne, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithTimeBasedPriority()
    {
        $messageOne = array('key' => 0);
        $messageTwo = array('key' => 1);
        $messageThree = array('key' => 2);

        $this->_queue->send($messageOne);
        $this->_queue->send($messageTwo);
        $this->_queue->send($messageThree);

        $resultOne = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultTwo = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultThree = $this->_queue->get(array(), PHP_INT_MAX, 0);

        $this->assertSame(array('id' => $resultOne['id']) + $messageOne, $resultOne);
        $this->assertSame(array('id' => $resultTwo['id']) + $messageTwo, $resultTwo);
        $this->assertSame(array('id' => $resultThree['id']) + $messageThree, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithTimeBasedPriorityWithOldTimestamp()
    {
        $messageOne = array('key' => 0);
        $messageTwo = array('key' => 1);
        $messageThree = array('key' => 2);

        $this->_queue->send($messageOne);
        $this->_queue->send($messageTwo);
        $this->_queue->send($messageThree);

        $resultTwo = $this->_queue->get(array(), PHP_INT_MAX, 0);
        //ensuring using old timestamp shouldn't affect normal time order of send()s
        $this->_queue->requeue($resultTwo, 0, 0.0, false);

        $resultOne = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultTwo = $this->_queue->get(array(), PHP_INT_MAX, 0);
        $resultThree = $this->_queue->get(array(), PHP_INT_MAX, 0);

        $this->assertSame(array('id' => $resultOne['id']) + $messageOne, $resultOne);
        $this->assertSame(array('id' => $resultTwo['id']) + $messageTwo, $resultTwo);
        $this->assertSame(array('id' => $resultThree['id']) + $messageThree, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWait()
    {
        $start = microtime(true);

        $this->_queue->get(array(), PHP_INT_MAX, 200);

        $end = microtime(true);

        $this->assertTrue($end - $start >= 0.200);
        $this->assertTrue($end - $start < 0.300);
    }

    /**
     * @test
     * @covers ::get
     */
    public function earliestGet()
    {
         $messageOne = array('key1' => 0, 'key2' => true);

         $this->_queue->send($messageOne, time() + 1);

         $this->assertNull($this->_queue->get($messageOne, PHP_INT_MAX, 0));

         sleep(1);

         $this->assertNotNull($this->_queue->get($messageOne, PHP_INT_MAX, 0));
    }

    /**
     * @test
     * @covers ::get
     */
    public function resetStuck()
    {
        $messageOne = array('key' => 0);
        $messageTwo = array('key' => 1);

        $this->_queue->send($messageOne);
        $this->_queue->send($messageTwo);

        //sets to running
        $this->_collection->update(array('payload.key' => 0), array('$set' => array('running' => true, 'resetTimestamp' => new \MongoDate())));
        $this->_collection->update(array('payload.key' => 1), array('$set' => array('running' => true, 'resetTimestamp' => new \MongoDate())));

        $this->assertSame(2, $this->_collection->count(array('running' => true)));

        //sets resetTimestamp on messageOne
        $this->_queue->get($messageOne, 0, 0);

        //resets and gets messageOne
        $this->assertNotNull($this->_queue->get($messageOne, PHP_INT_MAX, 0));

        $this->assertSame(1, $this->_collection->count(array('running' => false)));
    }

    /**
     * @test
     * @covers ::count
     * @expectedException \InvalidArgumentException
     */
    public function countWithNonNullOrBoolRunning()
    {
        $this->_queue->count(array(), 1);
    }

    /**
     * @test
     * @covers ::count
     * @expectedException \InvalidArgumentException
     */
    public function countWithNonStringKey()
    {
        $this->_queue->count(array(0 => 'a value'));
    }

    /**
     * @test
     * @covers ::count
     */
    public function testCount()
    {
        $message = array('boo' => 'scary');

        $this->assertSame(0, $this->_queue->count($message, true));
        $this->assertSame(0, $this->_queue->count($message, false));
        $this->assertSame(0, $this->_queue->count($message));

        $this->_queue->send($message);
        $this->assertSame(1, $this->_queue->count($message, false));
        $this->assertSame(0, $this->_queue->count($message, true));
        $this->assertSame(1, $this->_queue->count($message));

        $this->_queue->get($message, PHP_INT_MAX, 0);
        $this->assertSame(0, $this->_queue->count($message, false));
        $this->assertSame(1, $this->_queue->count($message, true));
        $this->assertSame(1, $this->_queue->count($message));
    }

    /**
     * @test
     * @covers ::ack
     */
    public function ack()
    {
        $messageOne = array('key1' => 0, 'key2' => true);

        $this->_queue->send($messageOne);
        $this->_queue->send(array('key' => 'value'));

        $result = $this->_queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertSame(2, $this->_collection->count());

        $this->_queue->ack($result);
        $this->assertSame(1, $this->_collection->count());
    }

    /**
     * @test
     * @covers ::ack
     * @expectedException \InvalidArgumentException
     */
    public function ackBadArg()
    {
        $this->_queue->ack(array('id' => new \stdClass()));
    }

    /**
     * @test
     * @covers ::ackSend
     */
    public function ackSend()
    {
        $messageOne = array('key1' => 0, 'key2' => true);
        $messageThree = array('hi' => 'there', 'rawr' => 2);

        $this->_queue->send($messageOne);
        $this->_queue->send(array('key' => 'value'));

        $resultOne = $this->_queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertSame(2, $this->_collection->count());

        $this->_queue->ackSend($resultOne, $messageThree);
        $this->assertSame(2, $this->_collection->count());

        $actual = $this->_queue->get(array('hi' => 'there'), PHP_INT_MAX, 0);
        $expected = array('id' => $resultOne['id']) + $messageThree;

        $actual['id'] = $actual['id']->__toString();
        $expected['id'] = $expected['id']->__toString();
        $this->assertSame($expected, $actual);
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithWrongIdType()
    {
        $this->_queue->ackSend(array('id' => 5), array());
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithNanPriority()
    {
        $this->_queue->ackSend(array('id' => new \MongoId()), array(), 0, NAN);
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithNonFloatPriority()
    {
        $this->_queue->ackSend(array('id' => new \MongoId()), array(), 0, 'NotAFloat');
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithNonIntEarliestGet()
    {
        $this->_queue->ackSend(array('id' => new \MongoId()), array(), true);
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithNonBoolNewTimestamp()
    {
        $this->_queue->ackSend(array('id' => new \MongoId()), array(), 0, 0.0, 1);
    }

    /**
     * @test
     * @covers ::ackSend
     */
    public function ackSendWithHighEarliestGet()
    {
        $this->_queue->send(array());
        $messageToAck = $this->_queue->get(array(), PHP_INT_MAX, 0);

        $this->_queue->ackSend($messageToAck, array(), PHP_INT_MAX);

        $expected = array(
            'payload' => array(),
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => Queue::MONGO_INT32_MAX,
            'priority' => 0.0,
        );

        $message = $this->_collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->sec);
        $this->assertGreaterThan(time() - 10, $message['created']->sec);

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = $message['resetTimestamp']->sec;
        $message['earliestGet'] = $message['earliestGet']->sec;

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::ackSend
     */
    public function ackSendWithLowEarliestGet()
    {
        $this->_queue->send(array());
        $messageToAck = $this->_queue->get(array(), PHP_INT_MAX, 0);

        $this->_queue->ackSend($messageToAck, array(), -1);

        $expected = array(
            'payload' => array(),
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => 0,
            'priority' => 0.0,
        );

        $message = $this->_collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->sec);
        $this->assertGreaterThan(time() - 10, $message['created']->sec);

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = $message['resetTimestamp']->sec;
        $message['earliestGet'] = $message['earliestGet']->sec;

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::requeue
     */
    public function requeue()
    {
        $messageOne = array('key1' => 0, 'key2' => true);

        $this->_queue->send($messageOne);
        $this->_queue->send(array('key' => 'value'));

        $resultBeforeRequeue = $this->_queue->get($messageOne, PHP_INT_MAX, 0);

        $this->_queue->requeue($resultBeforeRequeue);
        $this->assertSame(2, $this->_collection->count());

        $resultAfterRequeue = $this->_queue->get($messageOne, 0);
        $this->assertSame(array('id' => $resultAfterRequeue['id']) + $messageOne, $resultAfterRequeue);
    }

    /**
     * @test
     * @covers ::requeue
     * @expectedException \InvalidArgumentException
     */
    public function requeueBadArg()
    {
        $this->_queue->requeue(array('id' => new \stdClass()));
    }

    /**
     * @test
     * @covers ::send
     */
    public function send()
    {
        $payload = array('key1' => 0, 'key2' => true);
        $this->_queue->send($payload, 34, 0.8);

        $expected = array(
            'payload' => $payload,
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => 34,
            'priority' => 0.8,
        );

        $message = $this->_collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->sec);
        $this->assertGreaterThan(time() - 10, $message['created']->sec);

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = $message['resetTimestamp']->sec;
        $message['earliestGet'] = $message['earliestGet']->sec;

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::send
     * @expectedException \InvalidArgumentException
     */
    public function sendWithNanPriority()
    {
        $this->_queue->send(array(), 0, NAN);
    }

    /**
     * @test
     * @covers ::send
     * @expectedException \InvalidArgumentException
     */
    public function sendWithNonIntegerEarliestGet()
    {
        $this->_queue->send(array(), true);
    }

    /**
     * @test
     * @covers ::send
     * @expectedException \InvalidArgumentException
     */
    public function sendWithNonFloatPriority()
    {
        $this->_queue->send(array(), 0, new \stdClass());
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithHighEarliestGet()
    {
        $this->_queue->send(array(), PHP_INT_MAX);

        $expected = array(
            'payload' => array(),
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => Queue::MONGO_INT32_MAX,
            'priority' => 0.0,
        );

        $message = $this->_collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->sec);
        $this->assertGreaterThan(time() - 10, $message['created']->sec);

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = $message['resetTimestamp']->sec;
        $message['earliestGet'] = $message['earliestGet']->sec;

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithLowEarliestGet()
    {
        $this->_queue->send(array(), -1);

        $expected = array(
            'payload' => array(),
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => 0,
            'priority' => 0.0,
        );

        $message = $this->_collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->sec);
        $this->assertGreaterThan(time() - 10, $message['created']->sec);

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = $message['resetTimestamp']->sec;
        $message['earliestGet'] = $message['earliestGet']->sec;

        $this->assertSame($expected, $message);
    }
}
