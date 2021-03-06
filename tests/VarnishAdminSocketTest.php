<?php

use VarnishAdmin\VarnishAdminSocket;

class VarnishAdminSocketTest extends PHPUnit_Framework_TestCase
{
    /** @var VarnishAdminSocketFake */
    public $admin;

    public function setUp()
    {
        $this->admin = new VarnishAdminSocketFake();
    }

    public function testConstructDefaultValues()
    {
        $this->assertSame($this->admin->host, '127.0.0.1');
        $this->assertSame($this->admin->port, 6082);
        $this->assertSame($this->admin->version, 3);
    }

    public function testConstructVersion4Values()
    {
        $admin = new VarnishAdminSocketFake('127.0.0.1', 6082, '4.0.2');
        $this->assertSame($admin->host, '127.0.0.1');
        $this->assertSame($admin->port, 6082);
        $this->assertSame($admin->version, 4);
    }

    /**
     * @expectedException Exception
     */
    public function testConstructNoSupportedVarnishVersion()
    {
        new VarnishAdminSocketFake(1, 1, 5);
    }

    public function testCloseConnection()
    {
        $this->admin->close();
        $this->assertNull($this->admin->fp);
    }

    public function testConnectOk()
    {
        $this->admin->codeMock = 200;
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws \VarnishAdmin\Exception
     * @expectedException Exception
     * @expectedExceptionMessage Authentication required; see VarnishAdminSocket::setSecret
     */
    public function testConnectAuthenticationRequiredNotSecretDefined()
    {
        $this->admin->codeMock = 107;
        $this->admin->secret = false;
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws \VarnishAdmin\Exception
     * @expectedException Exception
     * @expectedExceptionMessage Authentication failed
     */
    public function testConnectAuthenticationFailed()
    {
        $this->admin->codeMock = 107;
        $this->admin->secret = true;
        $this->admin->commandResultException = 'Authentication failed';
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws \VarnishAdmin\Exception
     * @expectedException Exception
     * @expectedExceptionMessage Bad response from varnishadm on 127.0.0.1:6082
     */
    public function testConnectBadResponse()
    {
        $this->admin->codeMock = 503;
        $this->admin->secret = true;
        $this->admin->commandResultException = sprintf('Bad response from varnishadm on %s:%s', $this->admin->host,
            $this->admin->port);
        $this->assertNull($this->admin->connect());
    }

    public function testPurgeCommand()
    {
        $result = $this->admin->purge('expr');
        $this->assertEquals('ban expr', $result);
    }

    public function testPurgeUrlCommand()
    {
        $result = $this->admin->purgeUrl('http://example.com');
        $this->assertEquals('ban.url http://example.com', $result);
    }

    public function testPurgeUrlVarnish4Command()
    {
        $admin = new VarnishAdminSocketFake(1, 1, 4);
        $result = $admin->purgeUrl('http://example.com');
        $this->assertEquals('ban req.url ~ http://example.com', $result);
    }

    public function testQuit()
    {
        $this->admin->quit();
        $this->assertNull($this->admin->fp);
        $this->assertContains('quit', $this->admin->commandExecuted);

    }

    public function testStart()
    {
        $this->assertEquals(true, $this->admin->start());
        $this->assertContains('start', $this->admin->commandExecuted);
    }

    public function testStartWhenRunning()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, @$this->admin->start());
    }

    public function testStatusNotRunning()
    {
        $this->admin->isRunningMock = false;
        $this->assertEquals(false, $this->admin->status());
    }

    /**
     * @expectedException Exception;
     */
    public function testStatusNotRunningWithException()
    {
        $this->admin->isRunningMock = false;
        $this->admin->commandResultException = new Exception();
        $this->assertEquals(false, $this->admin->status());
    }

    public function testStatusRunning()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, $this->admin->status());
    }

    public function testSetSecret()
    {
        $this->admin->setSecret('secret');
        $this->assertEquals('secret', $this->admin->secret);
    }

    public function testStop()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, $this->admin->stop());
        $this->assertContains('stop', $this->admin->commandExecuted);
    }

    public function testStopWhenNotRunning()
    {
        $this->assertEquals(true, @$this->admin->stop());
    }
}

class VarnishAdminSocketFake extends VarnishAdminSocket
{
    public $host;
    public $port;
    public $fp;
    public $secret;
    public $version;

    //Mocks
    public $codeMock;
    public $commandResultException;
    public $commandExecuted = array();
    public $isRunningMock;

    protected function openSocket($timeout)
    {
    }

    protected function read(&$code)
    {
        $code = $this->codeMock;
    }

    protected function command($cmd, $code = '', $ok = 200)
    {
        if (isset($this->commandResultException)) {
            throw new Exception($this->commandResultException);
        }
        $this->commandExecuted[] = $cmd;

        return $cmd;
    }

    protected function isRunning($response)
    {
        return $this->isRunningMock;
    }
}
