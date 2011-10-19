<?php
namespace Everyman\Neo4j;

class ClientTest extends \PHPUnit_Framework_TestCase
{
	protected $transport = null;
	protected $client = null;
	protected $endpoint = 'http://foo:1234/db/data';

	public function setUp()
	{
		$this->transport = $this->getMock('Everyman\Neo4j\Transport');
		$this->transport->expects($this->any())
			->method('getEndpoint')
			->will($this->returnValue($this->endpoint));
		$this->client = new Client($this->transport);
	}

	/**
	 * @dataProvider dataProvider_DeleteNodeScenarios
	 */
	public function testDeleteNode_TransportResult_ReturnsCorrectSuccessOrFailure($result, $success, $error)
	{
		$node = new Node($this->client);
		$node->setId(123);
		
		$this->transport->expects($this->once())
			->method('delete')
			->with('/node/123')
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->deleteNode($node));
		$this->assertEquals($error, $this->client->getLastError());
	}

	public function dataProvider_DeleteNodeScenarios()
	{
		return array(// result, success, error
			array(array('code'=>204), true, null),
			array(array('code'=>404), false, Client::ErrorNotFound),
			array(array('code'=>409), false, Client::ErrorConflict),
		);
	}

	public function testDeleteNode_NodeHasNoId_ThrowsException()
	{
		$node = new Node($this->client);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->deleteNode($node);
	}

	public function testSaveNode_Update_NodeHasNoId_ThrowsException()
	{
		$node = new Node($this->client);
		$command = new Command\UpdateNode($this->client, $node);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$command->execute();
	}

	/**
	 * @dataProvider dataProvider_UpdateNodeScenarios
	 */
	public function testSaveNode_Update_ReturnsCorrectSuccessOrFailure($result, $success, $error)
	{
		$properties = array(
			'foo' => 'bar',
			'baz' => 'qux',
		);

		$node = new Node($this->client);
		$node->setId(123)
			->setProperties($properties);
		
		$this->transport->expects($this->once())
			->method('put')
			->with('/node/123/properties', $properties)
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->saveNode($node));
		$this->assertEquals($error, $this->client->getLastError());
		$this->assertEquals(123, $node->getId());
	}

	public function dataProvider_UpdateNodeScenarios()
	{
		return array(// result, success, error
			array(array('code'=>204), true, null),
			array(array('code'=>404), false, Client::ErrorNotFound),
			array(array('code'=>400), false, Client::ErrorBadRequest),
		);
	}

	/**
	 * @dataProvider dataProvider_CreateNodeScenarios
	 */
	public function testSaveNode_Create_ReturnsCorrectSuccessOrFailure($result, $success, $error, $id)
	{
		$properties = array(
			'foo' => 'bar',
			'baz' => 'qux',
		);

		$node = new Node($this->client);
		$node->setProperties($properties);
		
		$this->transport->expects($this->once())
			->method('post')
			->with('/node', $properties)
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->saveNode($node));
		$this->assertEquals($error, $this->client->getLastError());
		$this->assertEquals($id, $node->getId());
	}

	public function dataProvider_CreateNodeScenarios()
	{
		return array(// result, success, error, id
			array(array('code'=>201, 'headers'=>array('Location'=>'http://foo.com:1234/db/data/node/123')), true, null, 123),
			array(array('code'=>400), false, Client::ErrorBadRequest, null),
		);
	}

	public function testSaveNode_CreateNoProperties_ReturnsSuccess()
	{
		$node = new Node($this->client);
		
		$this->transport->expects($this->once())
			->method('post')
			->with('/node',null)
			->will($this->returnValue(array('code'=>201, 'headers'=>array('Location'=>'http://foo.com:1234/db/data/node/123'))));

		$this->assertTrue($this->client->saveNode($node));
		$this->assertEquals(123, $node->getId());
	}

	public function testGetNode_NotFound_ReturnsNull()
	{
		$nodeId = 123;
		
		$this->transport->expects($this->once())
			->method('get')
			->with('/node/'.$nodeId.'/properties')
			->will($this->returnValue(array('code'=>'404')));

		$this->assertNull($this->client->getNode($nodeId));
		$this->assertEquals(Client::ErrorNotFound, $this->client->getLastError());
	}

	public function testGetNode_Force_ReturnsNode()
	{
		$nodeId = 123;
		
		$this->transport->expects($this->never())
			->method('get');

		$node = $this->client->getNode($nodeId, true);
		$this->assertInstanceOf('Everyman\Neo4j\Node', $node);
		$this->assertEquals($nodeId, $node->getId());
		$this->assertNull($this->client->getLastError());
	}

	public function testGetNode_Found_ReturnsNode()
	{
		$nodeId = 123;
		$properties = array(
			'foo' => 'bar',
			'baz' => 'qux',
		);
		
		$this->transport->expects($this->once())
			->method('get')
			->with('/node/'.$nodeId.'/properties')
			->will($this->returnValue(array('code'=>'200','data'=>$properties)));

		$node = $this->client->getNode($nodeId);
		$this->assertNotNull($node);
		$this->assertInstanceOf('Everyman\Neo4j\Node', $node);
		$this->assertEquals($nodeId, $node->getId());
		$this->assertEquals($properties, $node->getProperties());
		$this->assertNull($this->client->getLastError());
	}

	public function testLoadNode_NodeHasNoId_ThrowsException()
	{
		$node = new Node($this->client);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->loadNode($node);
	}

	public function testGetRelationship_NotFound_ReturnsNull()
	{
		$relId = 123;
		
		$this->transport->expects($this->once())
			->method('get')
			->with('/relationship/'.$relId)
			->will($this->returnValue(array('code'=>'404')));

		$this->assertNull($this->client->getRelationship($relId));
		$this->assertEquals(Client::ErrorNotFound, $this->client->getLastError());
	}

	public function testGetRelationship_Force_ReturnsRelationship()
	{
		$relId = 123;
		
		$this->transport->expects($this->never())
			->method('get');

		$rel = $this->client->getRelationship($relId, true);
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rel);
		$this->assertEquals($relId, $rel->getId());
		$this->assertNull($this->client->getLastError());
	}

	public function testGetRelationship_Found_ReturnsRelationship()
	{
		$relId = 123;
		$data = array(
			'data' => array(
				'foo' => 'bar',
				'baz' => 'qux',
			),
			'start' => 'http://foo:1234/db/data/node/567',
			'end'   => 'http://foo:1234/db/data/node/890',
			'type'  => 'FOOTYPE',
		);
		
		$this->transport->expects($this->once())
			->method('get')
			->with('/relationship/'.$relId)
			->will($this->returnValue(array('code'=>'200','data'=>$data)));

		$rel = $this->client->getRelationship($relId);
		$this->assertNotNull($rel);
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rel);
		$this->assertEquals($relId, $rel->getId());
		$this->assertEquals($data['data'], $rel->getProperties());
		$this->assertEquals($data['type'], $rel->getType());
		$this->assertNull($this->client->getLastError());

		$start = $rel->getStartNode();
		$this->assertNotNull($start);
		$this->assertInstanceOf('Everyman\Neo4j\Node', $start);
		$this->assertEquals(567, $start->getId());

		$end = $rel->getEndNode();
		$this->assertNotNull($end);
		$this->assertInstanceOf('Everyman\Neo4j\Node', $end);
		$this->assertEquals(890, $end->getId());
	}

	public function testLoadRelationship_RelationshipHasNoId_ThrowsException()
	{
		$rel = new Relationship($this->client);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->loadRelationship($rel);
	}

	/**
	 * @dataProvider dataProvider_DeleteRelationshipScenarios
	 */
	public function testDeleteRelationship_TransportResult_ReturnsCorrectSuccessOrFailure($result, $success, $error)
	{
		$rel = new Relationship($this->client);
		$rel->setId(123);
		
		$this->transport->expects($this->once())
			->method('delete')
			->with('/relationship/123')
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->deleteRelationship($rel));
		$this->assertEquals($error, $this->client->getLastError());
	}

	public function dataProvider_DeleteRelationshipScenarios()
	{
		return array(// result, success, error
			array(array('code'=>204), true, null),
			array(array('code'=>404), false, Client::ErrorNotFound),
		);
	}

	public function testDeleteRelationship_RelationshipHasNoId_ThrowsException()
	{
		$rel = new Relationship($this->client);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->deleteRelationship($rel);
	}

	public function testSaveRelationship_Create_NoStartNode_ThrowsException()
	{
		$rel = new Relationship($this->client);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->saveRelationship($rel);
	}

	public function testSaveRelationship_Create_NoEndNode_ThrowsException()
	{
		$start = new Node($this->client);
		$start->setId(123);

		$rel = new Relationship($this->client);
		$rel->setStartNode($start);
		
		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->saveRelationship($rel);
	}

	public function testSaveRelationship_Create_NoType_ThrowsException()
	{
		$start = new Node($this->client);
		$start->setId(123);
		$end = new Node($this->client);
		$end->setId(456);

		$rel = new Relationship($this->client);
		$rel->setStartNode($start);
		$rel->setEndNode($end);
		
		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->saveRelationship($rel);
	}

	/**
	 * @dataProvider dataProvider_CreateRelationshipScenarios
	 */
	public function testSaveRelationship_Create_ReturnsCorrectSuccessOrFailure($result, $success, $error, $id)
	{
		$data = array(
			'data' => array(
				'foo' => 'bar',
				'baz' => 'qux',
			),
			'to' => $this->endpoint.'/node/456',
			'type' => 'FOOTYPE',
		);

		$start = new Node($this->client);
		$start->setId(123);
		$end = new Node($this->client);
		$end->setId(456);

		$rel = new Relationship($this->client);
		$rel->setType('FOOTYPE')
			->setStartNode($start)
			->setEndNode($end)
			->setProperties($data['data']);

		$this->transport->expects($this->once())
			->method('post')
			->with('/node/123/relationships', $data)
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->saveRelationship($rel));
		$this->assertEquals($error, $this->client->getLastError());
		$this->assertEquals($id, $rel->getId());
	}

	public function dataProvider_CreateRelationshipScenarios()
	{
		return array(// result, success, error, id
			array(array('code'=>201, 'headers'=>array('Location'=>'http://foo.com:1234/db/data/relationship/890')), true, null, 890),
			array(array('code'=>400), false, Client::ErrorBadRequest, null),
			array(array('code'=>404), false, Client::ErrorNotFound, null),
		);
	}

	/**
	 * @dataProvider dataProvider_CreateRelationshipScenarios
	 */
	public function testSaveRelationship_CreateNoData_ReturnsCorrectSuccesOrFailure($result, $success, $error, $id)
	{
		$data = array(
			'to' => $this->endpoint.'/node/456',
			'type' => 'FOOTYPE',
		);

		$start = new Node($this->client);
		$start->setId(123);
		$end = new Node($this->client);
		$end->setId(456);

		$rel = new Relationship($this->client);
		$rel->setType('FOOTYPE')
			->setStartNode($start)
			->setEndNode($end)
			->setProperties(array());

		$this->transport->expects($this->once())
			->method('post')
			->with('/node/123/relationships', $data)
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->saveRelationship($rel));
		$this->assertEquals($error, $this->client->getLastError());
		$this->assertEquals($id, $rel->getId());
	}

	public function testSaveRelationship_Update_RelationshipHasNoId_ThrowsException()
	{
		$rel = new Relationship($this->client);
		$command = new Command\UpdateRelationship($this->client, $rel);

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$command->execute();
	}

	/**
	 * @dataProvider dataProvider_UpdateRelationshipScenarios
	 */
	public function testSaveRelationship_Update_ReturnsCorrectSuccessOrFailure($result, $success, $error)
	{
		$properties = array(
			'foo' => 'bar',
			'baz' => 'qux',
		);

		$rel = new Relationship($this->client);
		$rel->setId(123)
			->setProperties($properties);
		
		$this->transport->expects($this->once())
			->method('put')
			->with('/relationship/123/properties', $properties)
			->will($this->returnValue($result));

		$this->assertEquals($success, $this->client->saveRelationship($rel));
		$this->assertEquals($error, $this->client->getLastError());
		$this->assertEquals(123, $rel->getId());
	}

	public function dataProvider_UpdateRelationshipScenarios()
	{
		return array(// result, success, error
			array(array('code'=>204), true, null),
			array(array('code'=>404), false, Client::ErrorNotFound),
			array(array('code'=>400), false, Client::ErrorBadRequest),
		);
	}

	public function testGetNodeRelationships_NodeNotPersisted_ThrowsException()
	{
		$node = new Node($this->client);
		$type = 'FOOTYPE';
		$dir = Relationship::DirectionOut;

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->getNodeRelationships($node, $type, $dir);
	}

	public function testGetNodeRelationships_NodeNotFound_ReturnsFalse()
	{
		$node = new Node($this->client);
		$node->setId(123);

		$this->transport->expects($this->once())
			->method('get')
			->with('/node/123/relationships/all')
			->will($this->returnValue(array('code'=>404)));

		$this->assertFalse($this->client->getNodeRelationships($node, array(), null));
		$this->assertEquals(Client::ErrorNotFound, $this->client->getLastError());
	}

	public function testGetNodeRelationships_NoRelationships_ReturnsEmptyArray()
	{
		$node = new Node($this->client);
		$node->setId(123);
		$types = array('FOOTYPE');
		$dir = Relationship::DirectionIn;

		$this->transport->expects($this->once())
			->method('get')
			->with('/node/123/relationships/in/FOOTYPE')
			->will($this->returnValue(array('code'=>200,'data'=>array())));

		$this->assertEquals(array(), $this->client->getNodeRelationships($node, $types, $dir));
		$this->assertNull($this->client->getLastError());
	}

	public function testGetNodeRelationships_Relationships_ReturnsArray()
	{
		$node = new Node($this->client);
		$node->setId(123);
		$types = array('FOOTYPE','BARTYPE');
		$dir = Relationship::DirectionOut;

		$data = array(
			array(
				"self" => "http://localhost:7474/db/data/relationship/56",
				"start" => "http://localhost:7474/db/data/node/123",
				"end" => "http://localhost:7474/db/data/node/93",
				"type" => "KNOWS",
				"data" => array('foo'=>'bar','baz'=>'qux'),
			),
			array(
				"self" => "http://localhost:7474/db/data/relationship/834",
				"start" => "http://localhost:7474/db/data/node/32",
				"end" => "http://localhost:7474/db/data/node/123",
				"type" => "LOVES",
				"data" => array('bar'=>'foo','qux'=>'baz'),
			),
		);

		$this->transport->expects($this->once())
			->method('get')
			->with('/node/123/relationships/out/FOOTYPE&BARTYPE')
			->will($this->returnValue(array('code'=>200,'data'=>$data)));

		$result = $this->client->getNodeRelationships($node, $types, $dir);
		$this->assertEquals(2, count($result));
		$this->assertNull($this->client->getLastError());

		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $result[0]);
		$this->assertEquals(56, $result[0]->getId());
		$this->assertEquals($data[0]['data'], $result[0]->getProperties());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $result[0]->getStartNode());
		$this->assertEquals(123, $result[0]->getStartNode()->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $result[0]->getEndNode());
		$this->assertEquals(93, $result[0]->getEndNode()->getId());

		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $result[1]);
		$this->assertEquals(834, $result[1]->getId());
		$this->assertEquals($data[1]['data'], $result[1]->getProperties());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $result[1]->getStartNode());
		$this->assertEquals(32, $result[1]->getStartNode()->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $result[1]->getEndNode());
		$this->assertEquals(123, $result[1]->getEndNode()->getId());
	}
	
	public function testGetPaths_PathsExist_ReturnsArray()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setType('FOOTYPE')
			->setDirection(Relationship::DirectionOut)
			->setMaxDepth(3)
			->setStartNode($startNode)
			->setEndNode($endNode);
		
		$data = array(
			'to' => $this->endpoint.'/node/456',
			'relationships' => array('type'=>'FOOTYPE', 'direction'=>Relationship::DirectionOut),
			'max_depth' => 3,
			'max depth' => 3,
			'algorithm' => 'shortestPath'
		);
		
		$returnData = array(
			array(
				"start" => "http://localhost:7474/db/data/node/123",
				"nodes" => array("http://localhost:7474/db/data/node/123", "http://localhost:7474/db/data/node/341", "http://localhost:7474/db/data/node/456"),
				"length" => 2,
				"relationships" => array("http://localhost:7474/db/data/relationship/564", "http://localhost:7474/db/data/relationship/32"),
				"end" => "http://localhost:7474/db/data/node/456"
			),
			array(
				"start" => "http://localhost:7474/db/data/node/123",
				"nodes" => array("http://localhost:7474/db/data/node/123", "http://localhost:7474/db/data/node/41", "http://localhost:7474/db/data/node/456"),
				"length" => 2,
				"relationships" => array("http://localhost:7474/db/data/relationship/437", "http://localhost:7474/db/data/relationship/97"),
				"end" => "http://localhost:7474/db/data/node/456"
			),
		);
		
		$this->transport->expects($this->once())
			->method('post')
			->with('/node/123/paths', $data)
			->will($this->returnValue(array('code'=>200,'data'=>$returnData)));
			
		$paths = $this->client->getPaths($finder);
		$this->assertEquals(2, count($paths));
		$this->assertInstanceOf('Everyman\Neo4j\Path', $paths[0]);
		$this->assertInstanceOf('Everyman\Neo4j\Path', $paths[1]);
		
		$rels = $paths[0]->getRelationships();
		$this->assertEquals(2, count($rels));
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rels[0]);
		$this->assertEquals(564, $rels[0]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rels[1]);
		$this->assertEquals(32, $rels[1]->getId());

		$nodes = $paths[0]->getNodes();
		$this->assertEquals(3, count($nodes));
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[0]);
		$this->assertEquals(123, $nodes[0]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[1]);
		$this->assertEquals(341, $nodes[1]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[2]);
		$this->assertEquals(456, $nodes[2]->getId());
		
		$rels = $paths[1]->getRelationships();
		$this->assertEquals(2, count($rels));
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rels[0]);
		$this->assertEquals(437, $rels[0]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Relationship', $rels[1]);
		$this->assertEquals(97, $rels[1]->getId());

		$nodes = $paths[1]->getNodes();
		$this->assertEquals(3, count($nodes));
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[0]);
		$this->assertEquals(123, $nodes[0]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[1]);
		$this->assertEquals(41, $nodes[1]->getId());
		$this->assertInstanceOf('Everyman\Neo4j\Node', $nodes[2]);
		$this->assertEquals(456, $nodes[2]->getId());
	}
	
	public function testGetPaths_NoMaxDepth_MaxDepthDefaultsToOne_ReturnsArray()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setType('FOOTYPE')
			->setDirection(Relationship::DirectionOut)
			->setStartNode($startNode)
			->setEndNode($endNode);
		
		$data = array(
			'to' => $this->endpoint.'/node/456',
			'relationships' => array('type'=>'FOOTYPE', 'direction'=>Relationship::DirectionOut),
			'max_depth' => 1,
			'max depth' => 1,
			'algorithm' => 'shortestPath'
		);
		
		$returnData = array();
		
		$this->transport->expects($this->once())
			->method('post')
			->with('/node/123/paths', $data)
			->will($this->returnValue(array('code'=>200,'data'=>$returnData)));
			
		$paths = $this->client->getPaths($finder);
		$this->assertEquals(0, count($paths));
	}

	public function testGetPaths_DirectionGivenButNoType_ThrowsException()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setDirection(Relationship::DirectionOut)
			->setStartNode($startNode)
			->setEndNode($endNode);
		
		$this->setExpectedException('\Everyman\Neo4j\Exception');			
		$paths = $this->client->getPaths($finder);
	}
	
	public function testGetPaths_StartNodeNotPersisted_ThrowsException()
	{
		$startNode = new Node($this->client);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setStartNode($startNode)
			->setEndNode($endNode);
		
		$this->setExpectedException('\Everyman\Neo4j\Exception');			
		$paths = $this->client->getPaths($finder);
	}
	
	public function testGetPaths_EndNodeNotPersisted_ThrowsException()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		
		$finder = new PathFinder($this->client);
		$finder->setStartNode($startNode)
			->setEndNode($endNode);
		
		$this->setExpectedException('\Everyman\Neo4j\Exception');			
		$paths = $this->client->getPaths($finder);
	}
	
	public function testGetPaths_DijkstraSearchNoCostProperty_ThrowsException()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setStartNode($startNode)
			->setEndNode($endNode)
			->setAlgorithm(PathFinder::AlgoDijkstra);
		
		$this->setExpectedException('\Everyman\Neo4j\Exception');			
		$paths = $this->client->getPaths($finder);
	}
	
	public function testGetPaths_DijkstraSearch_ReturnsResult()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setStartNode($startNode)
			->setEndNode($endNode)
			->setAlgorithm(PathFinder::AlgoDijkstra)
			->setCostProperty('distance')
			->setDefaultCost(2);
		
		$data = array(
			'to' => $this->endpoint.'/node/456',
			'max_depth' => 1,
			'max depth' => 1,
			'algorithm' => 'dijkstra',
			'cost_property' => 'distance',
			'cost property' => 'distance',
			'default_cost' => 2,
			'default cost' => 2,
		);
		
		$returnData = array();
		
		$this->transport->expects($this->once())
			->method('post')
			->with('/node/123/paths', $data)
			->will($this->returnValue(array('code'=>200,'data'=>$returnData)));
			
		$paths = $this->client->getPaths($finder);
		$this->assertEquals(0, count($paths));
	}
	
	public function testGetPaths_TransportFails_ReturnsFalse()
	{
		$startNode = new Node($this->client);
		$startNode->setId(123);
		$endNode = new Node($this->client);
		$endNode->setId(456);
		
		$finder = new PathFinder($this->client);
		$finder->setType('FOOTYPE')
			->setDirection(Relationship::DirectionOut)
			->setMaxDepth(3)
			->setStartNode($startNode)
			->setEndNode($endNode);
		
		$this->transport->expects($this->any())
			->method('post')
			->will($this->returnValue(array('code'=>400)));

		$this->assertFalse($this->client->getPaths($finder));
	}

	/**
	 * @dataProvider dataProvider_TestCypherQuery
	 */
	public function testCypherQuery($returnValue, $resultCount)
	{
		$props = array(
			'query' => 'START a=(0) RETURN a'
		);

		$this->transport->expects($this->once())
			->method('post')
			->with('/ext/CypherPlugin/graphdb/execute_query', $props)
			->will($this->returnValue($returnValue));

		$query = new Cypher\Query($this->client, 'START a=(?) RETURN a', array(0));

		$result = $this->client->executeCypherQuery($query);
		$this->assertEquals(count($result), $resultCount);
	}
	
	public function dataProvider_TestCypherQuery()
	{
		$return = array(
			'columns' => array('name','age'),
			'data' => array(
				array('Bob', 12),
				array('Lotta', 0),
				array('Brenda', 14)
			)
		);
		
		return array(
			array(array('code'=>204,'data'=>null), 0),
			array(array('code'=>200,'data'=>$return), 3),
		);
	}

	public function testCypherQuery_ServerReturnsErrorCode_ReturnsFalse()
	{
		$props = array(
			'query' => 'START a=(0) RETURN a'
		);

		$this->transport->expects($this->once())
			->method('post')
			->with('/ext/CypherPlugin/graphdb/execute_query', $props)
			->will($this->returnValue(array('code'=>Client::ErrorBadRequest)));

		$query = new Cypher\Query($this->client, 'START a=(?) RETURN a', array(0));

		$result = $this->client->executeCypherQuery($query);
		$this->assertFalse($result);
		$this->assertEquals(Client::ErrorBadRequest, $this->client->getLastError());
	}

	public function testGetRelationshipTypes_ServerReturnsErrorCode_ThrowsException()
	{
		$this->transport->expects($this->once())
			->method('get')
			->with('/relationship/types')
			->will($this->returnValue(array('code'=>Client::ErrorBadRequest)));

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$result = $this->client->getRelationshipTypes();
	}

	public function testGetRelationshipTypes_ServerReturnsArray_ReturnsArray()
	{
		$this->transport->expects($this->once())
			->method('get')
			->with('/relationship/types')
			->will($this->returnValue(array('code'=>200, 'data'=>array("foo","bar"))));

		$result = $this->client->getRelationshipTypes();
		$this->assertEquals(array("foo","bar"), $result);
	}

	public function testGetServerInfo_ServerReturnsArray_ReturnsArray()
	{
		$returnData = array(
			"relationship_index" => "http://localhost:7474/db/data/index/relationship",
			"node" => "http://localhost:7474/db/data/node",
			"relationship_types" => "http://localhost:7474/db/data/relationship/types",
			"batch" => "http://localhost:7474/db/data/batch",
			"extensions_info" => "http://localhost:7474/db/data/ext",
			"node_index" => "http://localhost:7474/db/data/index/node",
			"reference_node" => "http://localhost:7474/db/data/node/2",
			"extensions" => array(),
			"neo4j_version" => "1.5.M01-793-gc100417-dirty",
		);

		$expectedData = $returnData;
		$expectedData['version'] = array(
			"full" => "1.5.M01-793-gc100417-dirty",
			"major" => "1",
			"minor" => "5",
			"release" => "M01",
		);

		$this->transport->expects($this->once())
			->method('get')
			->with('/')
			->will($this->returnValue(array('code'=>200, 'data'=>$returnData)));

		$result = $this->client->getServerInfo();
		$this->assertEquals($expectedData, $result);
	}

	public function testGetServerInfo_UnsuccessfulResponse_ThrowsException()
	{
		$this->transport->expects($this->once())
			->method('get')
			->with('/')
			->will($this->returnValue(array('code'=>400)));

		$this->setExpectedException('Everyman\Neo4j\Exception');
		$this->client->getServerInfo();
	}
}
