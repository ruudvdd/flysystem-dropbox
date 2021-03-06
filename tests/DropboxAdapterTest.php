<?php

namespace Spatie\FlysystemDropbox\Test;

use Prophecy\Argument;
use Spatie\Dropbox\Client;
use League\Flysystem\Config;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Spatie\Dropbox\Exceptions\BadRequest;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxAdapterTest extends TestCase
{
    /** @var \Spatie\Dropbox\Client|\Prophecy\Prophecy\ObjectProphecy */
    protected $client;

    /** @var \Spatie\FlysystemDropbox\DropboxAdapter */
    protected $dropboxAdapter;

    public function setUp()
    {
        $this->client = $this->prophesize(Client::class);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal(), 'prefix');
    }

    /** @test */
    public function it_can_write()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'name' => 'something',
            'path_display' => '/prefix/something',
            'path_lower' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->dropboxAdapter->write('something', 'contents', new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_update()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'name' => 'something',
            'path_display' => '/prefix/something',
            'path_lower' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->dropboxAdapter->update('something', 'contents', new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_write_a_stream()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'name' => 'something',
            'path_display' => '/prefix/something',
            'path_lower' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->dropboxAdapter->writeStream('something', tmpfile(), new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /** @test */
    public function it_can_upload_using_a_stream()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'name' => 'something',
            'path_display' => '/prefix/something',
            'path_lower' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $this->dropboxAdapter->updateStream('something', tmpfile(), new Config());

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @test
     *
     * @dataProvider  metadataProvider
     */
    public function it_has_calls_to_get_meta_data($method)
    {
        $this->client = $this->prophesize(Client::class);
        $this->client->getMetadata('/one')->willReturn([
            '.tag'   => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'name' => 'one',
            'path_display' => '/one',
            'path_lower' => '/one',
        ]);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal());

        $this->assertInternalType('array', $this->dropboxAdapter->{$method}('one'));
    }

    public function metadataProvider(): array
    {
        return [
            ['getMetadata'],
            ['getTimestamp'],
            ['getSize'],
            ['has'],
        ];
    }

    /** @test */
    public function it_will_not_hold_metadata_after_failing()
    {
        $this->client = $this->prophesize(Client::class);

        $this->client->getMetadata('/one')->willThrow(new BadRequest(new Response(409)));

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal());

        $this->assertFalse($this->dropboxAdapter->has('one'));
    }

    /** @test */
    public function it_can_read()
    {
        $stream = tmpfile();
        fwrite($stream, 'something');

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertInternalType('array', $this->dropboxAdapter->read('something'));
    }

    /** @test */
    public function it_can_read_using_a_stream()
    {
        $stream = tmpfile();
        fwrite($stream, 'something');

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertInternalType('array', $this->dropboxAdapter->readStream('something'));

        fclose($stream);
    }

    /** @test */
    public function it_can_delete_stuff()
    {
        $this->client->delete('/prefix/something')->willReturn(['.tag' => 'file']);

        $this->assertTrue($this->dropboxAdapter->delete('something'));
        $this->assertTrue($this->dropboxAdapter->deleteDir('something'));
    }

    /** @test */
    public function it_can_create_a_directory()
    {
        $this->client->createFolder('/prefix/fail/please')->willThrow(new BadRequest(new Response(409)));
        $this->client->createFolder('/prefix/pass/please')->willReturn([
            '.tag' => 'folder',
            'name' => 'please',
            'path_display'   => '/prefix/pass/please',
            'path_lower' => '/prefix/pass/please',
        ]);

        $this->assertFalse($this->dropboxAdapter->createDir('fail/please', new Config()));

        $expected = ['path' => 'pass/please', 'type' => 'dir', 'name' => 'please', 'path_display' => 'pass/please'];
        $this->assertEquals($expected, $this->dropboxAdapter->createDir('pass/please', new Config()));
    }

    /** @test */
    public function it_can_list_a_single_page_of_contents()
    {
        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname', 'path_lower' => 'dirname', 'name' => 'dirname'],
                    ['.tag' => 'file', 'path_display' => 'dirname/file', 'path_lower' => 'dirname/file', 'name' => 'file'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->dropboxAdapter->listContents('', true);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_can_list_multiple_pages_of_contents()
    {
        $cursor = 'cursor';

        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname', 'path_lower' => 'dirname', 'name' => 'dirname'],
                    ['.tag' => 'file', 'path_display' => 'dirname/file', 'path_lower' => 'dirname/file', 'name' => 'file'],
                ],
                'has_more' => true,
                'cursor' => $cursor,
            ]
        );

        $this->client->listFolderContinue(Argument::exact($cursor))->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname2', 'path_lower' => 'dirname2', 'name' => 'dirname2'],
                    ['.tag' => 'file', 'path_display' => 'dirname2/file2', 'path_lower' => 'dirname2/file2', 'name' => 'file2'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->dropboxAdapter->listContents('', true);

        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_lists_the_paths_in_lower_case_and_the_returns_the_names_in_the_correct_case()
    {
        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => '/prefix/dirname', 'path_lower' => '/prefix/dirname', 'name' => 'Dirname'],
                    ['.tag' => 'file', 'path_display' => '/prefix/Dirname/File', 'path_lower' => '/prefix/dirname/file', 'name' => 'File'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->dropboxAdapter->listContents('', true);

        $this->assertCount(2, $result);

        $this->assertEquals($result[0]['name'], 'Dirname');
        $this->assertEquals($result[0]['path'], 'dirname');
        $this->assertEquals($result[0]['path_display'], 'dirname');

        $this->assertEquals($result[1]['name'], 'File');
        $this->assertEquals($result[1]['path'], 'dirname/file');
        $this->assertEquals($result[1]['path_display'], 'Dirname/File');
    }

    /** @test */
    public function it_can_rename_stuff()
    {
        $this->client->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something', 'path_lower' => 'something', 'name' => 'something']);

        $this->assertTrue($this->dropboxAdapter->rename('something', 'something'));
    }

    /** @test */
    public function it_will_return_false_when_a_rename_has_failed()
    {
        $this->client->move('/prefix/something', '/prefix/something')->willThrow(new BadRequest(new Response(409)));

        $this->assertFalse($this->dropboxAdapter->rename('something', 'something'));
    }

    /** @test */
    public function it_can_copy_a_file()
    {
        $this->client->copy(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->assertTrue($this->dropboxAdapter->copy('something', 'something'));
    }

    /** @test */
    public function it_will_return_false_when_the_copy_process_has_failed()
    {
        $this->client->copy(Argument::any(), Argument::any())->willThrow(new BadRequest(new Response(409)));

        $this->assertFalse($this->dropboxAdapter->copy('something', 'something'));
    }

    /** @test */
    public function it_can_get_a_client()
    {
        $this->assertInstanceOf(Client::class, $this->dropboxAdapter->getClient());
    }
}
