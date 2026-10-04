<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager\Tests;

use Bongo\Packages\FileManager\Controllers\FileManagerController;
use Bongo\Packages\FileManager\FileManager;
use Bongo\Packages\FileManager\Services\FileManagerService;
use Bongo\Packages\FileManager\Services\PathSecurity;
use System\Http\Request;
use System\Testing\TestCase;

require_once __DIR__ . '/../src/Helpers.php';

class FileManagerTest extends TestCase
{
    protected string $testDir;

    public function setUp(): void
    {
        $this->testDir = 'filemanager_test_' . uniqid();
        $service = new FileManagerService('public', $this->testDir);
        FileManager::setService($service);
    }

    public function tearDown(): void
    {
        $root = FileManager::service()->getItems('');
        foreach ($root['files'] as $f) {
            FileManager::deleteItem($f->path);
        }
        foreach ($root['folders'] as $f) {
            FileManager::deleteItem($f->path);
        }
    }

    public function testPathSecuritySanitization(): void
    {
        $this->assertSame('safe/path', PathSecurity::sanitize('../../safe/path/'));
        $this->assertSame('etc/passwd', PathSecurity::sanitize('/../../etc/passwd'));
        $this->assertSame('folder/file.php', PathSecurity::sanitize('folder\\..\\folder\\file.php'));
        $this->assertSame('file.txt', PathSecurity::sanitize("file.txt\0.exe"));
    }

    public function testPathSecurityFileTypeDetection(): void
    {
        $this->assertTrue(PathSecurity::isImage('photo.jpg'));
        $this->assertTrue(PathSecurity::isImage('banner.webp'));
        $this->assertFalse(PathSecurity::isImage('script.js'));

        $this->assertTrue(PathSecurity::isEditable('index.php'));
        $this->assertTrue(PathSecurity::isEditable('app.js'));
        $this->assertTrue(PathSecurity::isEditable('schema.sql'));
        $this->assertFalse(PathSecurity::isEditable('binary.zip'));

        $this->assertSame('php', PathSecurity::getEditorLanguage('test.php'));
        $this->assertSame('javascript', PathSecurity::getEditorLanguage('main.js'));
        $this->assertSame('sql', PathSecurity::getEditorLanguage('db.sql'));
        $this->assertSame('json', PathSecurity::getEditorLanguage('composer.json'));
    }

    public function testFolderCreationAndListing(): void
    {
        $created = FileManager::createFolder('', 'documents');
        $this->assertTrue($created);

        $createdSub = FileManager::createFolder('documents', 'invoices');
        $this->assertTrue($createdSub);

        $items = FileManager::getItems('');
        $this->assertCount(1, $items['folders']);
        $this->assertSame('documents', $items['folders'][0]->name);

        $subItems = FileManager::getItems('documents');
        $this->assertCount(1, $subItems['folders']);
        $this->assertSame('invoices', $subItems['folders'][0]->name);
    }

    public function testFileReadAndSaveCodeEditing(): void
    {
        $code = "<?php\necho 'Hello Bongo!';\n";
        $saved = FileManager::saveFile('scripts/app.php', $code);
        $this->assertTrue($saved);

        $content = FileManager::readFile('scripts/app.php');
        $this->assertSame($code, $content);

        // Update with new content
        $updatedCode = "<?php\necho 'Modified via Code Editor!';\n";
        FileManager::saveFile('scripts/app.php', $updatedCode);
        $this->assertSame($updatedCode, FileManager::readFile('scripts/app.php'));
    }

    public function testRenameAndMoveItem(): void
    {
        FileManager::saveFile('old_name.txt', 'test content');
        $this->assertSame('old_name.txt', FileManager::service()->getItems('')['files'][0]->name);

        // Rename
        $renamed = FileManager::renameItem('old_name.txt', 'new_name.txt');
        $this->assertTrue($renamed);

        $items = FileManager::getItems('');
        $this->assertSame('new_name.txt', $items['files'][0]->name);

        // Move to folder
        FileManager::createFolder('', 'archive');
        $moved = FileManager::moveItem('new_name.txt', 'archive');
        $this->assertTrue($moved);

        $archiveItems = FileManager::getItems('archive');
        $this->assertCount(1, $archiveItems['files']);
        $this->assertSame('new_name.txt', $archiveItems['files'][0]->name);
    }

    public function testDeleteItem(): void
    {
        FileManager::saveFile('delete_me.txt', 'temporary');
        $this->assertCount(1, FileManager::getItems('')['files']);

        $deleted = FileManager::deleteItem('delete_me.txt');
        $this->assertTrue($deleted);
        $this->assertCount(0, FileManager::getItems('')['files']);
    }

    public function testControllerItemsJsonEndpoint(): void
    {
        FileManager::createFolder('', 'photos');
        FileManager::saveFile('notes.txt', 'sample notes');

        $controller = new FileManagerController();
        $req = new Request();

        $res = $controller->items($req);
        $this->assertSame('success', $res['status']);
        $this->assertCount(1, $res['folders']);
        $this->assertCount(1, $res['files']);
        $this->assertSame('photos', $res['folders'][0]->name);
        $this->assertSame('notes.txt', $res['files'][0]->name);
    }

    public function testControllerReadAndSaveEndpoints(): void
    {
        FileManager::saveFile('config.json', '{"debug": true}');

        $controller = new FileManagerController();

        // 1. Read endpoint
        $_GET['path'] = 'config.json';
        $reqRead = new Request();
        $readRes = $controller->read($reqRead);

        $this->assertSame('success', $readRes['status']);
        $this->assertSame('json', $readRes['language']);
        $this->assertSame('{"debug": true}', $readRes['content']);

        // 2. Save endpoint
        $_POST['path'] = 'config.json';
        $_POST['content'] = '{"debug": false, "version": 2}';
        $reqSave = new Request();
        $saveRes = $controller->save($reqSave);

        $this->assertSame('success', $saveRes['status']);
        $this->assertSame('{"debug": false, "version": 2}', FileManager::readFile('config.json'));

        unset($_GET['path'], $_POST['path'], $_POST['content']);
    }

    public function testGlobalHelper(): void
    {
        $url = filemanager_url('image', 'pickerCallback');
        $this->assertContains('/filemanager', $url);
        $this->assertContains('type=image', $url);
        $this->assertContains('callback=pickerCallback', $url);
    }

    public function testDownloadEndpointReturns404OnMissingFile(): void
    {
        $controller = new FileManagerController();
        $_GET['path'] = 'non_existent_file.pdf';
        $req = new Request();

        ob_start();
        $controller->download($req);
        $output = ob_get_clean();

        $this->assertSame('File not found.', $output);
        unset($_GET['path']);
    }

    public function testThumbnailReturns404OnMissing(): void
    {
        $controller = new FileManagerController();
        $_GET['path'] = 'missing_image.png';
        $req = new Request();

        ob_start();
        $controller->thumbnail($req);
        $output = ob_get_clean();

        $this->assertSame('Image not found.', $output);
        unset($_GET['path']);
    }

    public function testThumbnailGeneratesValidImageOutput(): void
    {
        if (!extension_loaded('gd')) {
            $this->assertTrue(true);
            return;
        }

        $img = imagecreatetruecolor(10, 10);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefilledrectangle($img, 0, 0, 9, 9, $red);

        ob_start();
        imagepng($img);
        $pngData = (string) ob_get_clean();
        imagedestroy($img);

        FileManager::saveFile('test_photo.png', $pngData);

        $controller = new FileManagerController();
        $_GET['path'] = 'test_photo.png';
        $_GET['w'] = 50;
        $_GET['h'] = 50;
        $req = new Request();

        ob_start();
        $controller->thumbnail($req);
        $thumbData = (string) ob_get_clean();

        $this->assertTrue(strlen($thumbData) > 0);

        unset($_GET['path'], $_GET['w'], $_GET['h']);
    }
}
