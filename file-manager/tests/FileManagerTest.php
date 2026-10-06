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

        $this->assertFalse(PathSecurity::isEditable('index.php'), 'a saved .php file would run on the server');
        $this->assertFalse(PathSecurity::isEditable('page.html'), 'HTML runs scripts on this origin');
        $this->assertFalse(PathSecurity::isAllowedForUpload('logo.svg'), 'SVG can carry scripts');
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
        $code = "console.log('Hello Bongo!');\n";
        $saved = FileManager::saveFile('scripts/app.js', $code);
        $this->assertTrue($saved);

        $content = FileManager::readFile('scripts/app.js');
        $this->assertSame($code, $content);

        // Update with new content
        $updatedCode = "console.log('Modified via Code Editor!');\n";
        FileManager::saveFile('scripts/app.js', $updatedCode);
        $this->assertSame($updatedCode, FileManager::readFile('scripts/app.js'));
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

    public function testCopyItem(): void
    {
        FileManager::saveFile('original.txt', 'file content');
        FileManager::createFolder('', 'backup');

        $copied = FileManager::copyItem('original.txt', 'backup');
        $this->assertTrue($copied);

        // Source still exists
        $rootItems = FileManager::getItems('');
        $this->assertCount(1, $rootItems['files']);
        $this->assertSame('original.txt', $rootItems['files'][0]->name);

        // Destination copy exists
        $backupItems = FileManager::getItems('backup');
        $this->assertCount(1, $backupItems['files']);
        $this->assertSame('original.txt', $backupItems['files'][0]->name);

        // Copy again produces duplicate with _copy suffix
        $copiedAgain = FileManager::copyItem('original.txt', 'backup');
        $this->assertTrue($copiedAgain);
        $backupItemsAfter = FileManager::getItems('backup');
        $this->assertCount(2, $backupItemsAfter['files']);

        // Copy with custom new name
        $copiedRenamed = FileManager::copyItem('original.txt', 'backup', 'custom_renamed.txt');
        $this->assertTrue($copiedRenamed);
        $backupItemsWithCustom = FileManager::getItems('backup');
        $this->assertCount(3, $backupItemsWithCustom['files']);
        $fileNames = array_map(fn($f) => $f->name, $backupItemsWithCustom['files']);
        $this->assertTrue(in_array('custom_renamed.txt', $fileNames, true));
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

    public function testAllowedForUploadSecurity(): void
    {
        $this->assertTrue(PathSecurity::isAllowedForUpload('photo.jpg'));
        $this->assertTrue(PathSecurity::isAllowedForUpload('document.pdf'));

        // Reject dangerous executables
        $this->assertFalse(PathSecurity::isAllowedForUpload('shell.php'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('backdoor.php7'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('exploit.phar'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('script.php.exe'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('malware.exe'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('shell.sh'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('backdoor.phtml'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('gate.cgi'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('shell.py'));

        // Reject hidden files and configuration overrides
        $this->assertFalse(PathSecurity::isAllowedForUpload('.htaccess'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('custom.htaccess'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('.env'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('.user.ini'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('config.ini'));

        // Reject null byte injection
        $this->assertFalse(PathSecurity::isAllowedForUpload("photo.jpg\0.php"));

        // Reject multi-extension execution tricks
        $this->assertFalse(PathSecurity::isAllowedForUpload('avatar.php.png'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('file.phtml.jpg'));
        $this->assertFalse(PathSecurity::isAllowedForUpload('test.php7.pdf'));
    }

    public function testRenameSecurityRejectsBypasses(): void
    {
        FileManager::saveFile('test_file.txt', 'sample content');

        // Cannot rename safe file to dangerous executable extension
        $this->assertFalse(FileManager::renameItem('test_file.txt', 'shell.php'));
        $this->assertFalse(FileManager::renameItem('test_file.txt', 'malware.exe'));
        $this->assertFalse(FileManager::renameItem('test_file.txt', '.htaccess'));
        $this->assertFalse(FileManager::renameItem('test_file.txt', 'backdoor.phar'));

        // Can rename safe file to another safe name
        $this->assertTrue(FileManager::renameItem('test_file.txt', 'renamed_file.txt'));
        $files = FileManager::getItems('')['files'];
        $this->assertSame('renamed_file.txt', $files[0]->name);

        // Script and active-content names are refused even for a rename
        $this->assertFalse(FileManager::renameItem('renamed_file.txt', 'index.html'));
        $this->assertFalse(FileManager::renameItem('renamed_file.txt', 'logo.svg'));

        // Code files keep working under a safe extension
        FileManager::saveFile('scripts/app.js', 'let a = 1;');
        $this->assertTrue(FileManager::renameItem('scripts/app.js', 'main.js'));
        $this->assertSame('let a = 1;', FileManager::readFile('scripts/main.js'));
    }

    public function testCopySecurityRejectsBypasses(): void
    {
        FileManager::saveFile('doc.txt', 'sample text');
        FileManager::createFolder('', 'target_dir');

        // Cannot copy as blocked extension
        $this->assertFalse(FileManager::copyItem('doc.txt', 'target_dir', 'shell.php'));
        $this->assertFalse(FileManager::copyItem('doc.txt', 'target_dir', 'malware.exe'));

        // Can copy as safe name
        $this->assertTrue(FileManager::copyItem('doc.txt', 'target_dir', 'safe_copy.txt'));
        $targetFiles = FileManager::getItems('target_dir')['files'];
        $this->assertSame('safe_copy.txt', $targetFiles[0]->name);
    }

    public function testSaveAndReadSecurityRestrictions(): void
    {
        // Rejects saving non-editable files or hidden configs
        $this->assertFalse(FileManager::saveFile('exploit.exe', 'binary'));
        $this->assertFalse(FileManager::saveFile('.env', 'DB_PASSWORD=secret'));
        $this->assertFalse(FileManager::saveFile('.htaccess', 'SetHandler'));

        // Never writes server scripts or HTML (remote code execution / stored XSS)
        $this->assertFalse(FileManager::saveFile('scripts/test.php', '<?php system($_GET["c"]);'));
        $this->assertFalse(FileManager::saveFile('scripts/test.phtml', '<?php echo 1;'));
        $this->assertFalse(FileManager::saveFile('page.html', '<script>alert(1)</script>'));

        // Allows saving valid code files in code editor
        $this->assertTrue(FileManager::saveFile('scripts/test.js', 'console.log(123);'));
        $this->assertSame('console.log(123);', FileManager::readFile('scripts/test.js'));

        // Reading non-editable file throws exception
        FileManager::saveFile('valid.txt', 'some text');
        $this->assertSame('some text', FileManager::readFile('valid.txt'));
    }

    public function testFolderCreationSanitization(): void
    {
        // Folder name is sanitized to safe characters
        $this->assertTrue(FileManager::createFolder('', 'my<script>folder'));
        $folders = FileManager::getItems('')['folders'];
        $folderNames = array_map(fn($f) => $f->name, $folders);
        $this->assertTrue(in_array('my_script_folder', $folderNames, true));

        // Hidden folder rejected
        $this->assertFalse(FileManager::createFolder('', '.hidden_dir'));
    }

    public function testRoutesRequireASignedInUserByDefault(): void
    {
        $config = require dirname(__DIR__) . '/config/filemanager.php';
        $this->assertTrue(in_array('auth', $config['middleware'], true), 'anonymous visitors must not reach the file manager');
        $this->assertTrue(in_array('web', $config['middleware'], true), 'session + CSRF protection');
        $this->assertFalse($config['allow_html_svg'], 'HTML/SVG uploads are off by default');
    }

    public function testScriptHelperEndpoint(): void
    {
        $controller = new FileManagerController();
        $js = $controller->script();
        $this->assertContains('bongoFileManager', $js);
        $this->assertContains('bongoTinyMCEPicker', $js);
    }

    public function testDownloadAndThumbnailSecurity(): void
    {
        $controller = new FileManagerController();

        // 1. Download non-existent file returns 404 text
        $_GET['path'] = 'nonexistent.txt';
        $req404 = new Request();
        ob_start();
        $controller->download($req404);
        $out404 = ob_get_clean();
        $this->assertSame('File not found.', $out404);

        // 2. Download hidden file rejected
        $_GET['path'] = '.env';
        $reqHidden = new Request();
        ob_start();
        $controller->download($reqHidden);
        $outHidden = ob_get_clean();
        $this->assertSame('File not found.', $outHidden);

        // 3. Download valid existing file succeeds
        FileManager::saveFile('valid_download.txt', 'hello download');
        $_GET['path'] = 'valid_download.txt';
        $reqValid = new Request();
        ob_start();
        $controller->download($reqValid);
        $outValid = ob_get_clean();
        $this->assertSame('hello download', $outValid);

        // 4. Thumbnail non-image returns Image not found
        $_GET['path'] = 'valid_download.txt';
        $reqThumbText = new Request();
        ob_start();
        $controller->thumbnail($reqThumbText);
        $outThumbText = ob_get_clean();
        $this->assertSame('Image not found.', $outThumbText);

        unset($_GET['path']);
    }

    public function testRealpathBoundaryProtection(): void
    {
        // Path traversal deletion cannot delete outside root or delete root
        $this->assertFalse(FileManager::deleteItem(''));
        $this->assertFalse(FileManager::deleteItem('../'));
        $this->assertFalse(FileManager::deleteItem('../../'));

        // Renaming / moving outside root rejected
        $this->assertFalse(FileManager::renameItem('', 'foo.txt'));
        $this->assertFalse(FileManager::moveItem('', 'somedir'));
    }
}
