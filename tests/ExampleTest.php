<?php

use igaster\imageVersions\Tests\TestCase\TestCaseWithDatbase;
use Orchestra\Testbench\TestCase;


use igaster\imageVersions\Tests\App\Photo;
use igaster\imageVersions\Version;

use igaster\imageVersions\Tests\App\Transformations\v200x200;
use igaster\imageVersions\Tests\App\Transformations\vParameters;
use igaster\imageVersions\Tests\App\Transformations\vMissingMethod;

class ExampleTest extends TestCaseWithDatbase
{

    // -----------------------------------------------
    //   Global Setup(Run Once)
    // -----------------------------------------------

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        // Your Code here...
    }

    public static function tearDownAfterClass(){
        // Your Code here...
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------
    //  Setup Database (Run before each Test)
    // -----------------------------------------------

    public function setUp()
    {
        parent::setUp();

        // set the public path to this directory
        App::bind('path.public', function() {
            return __DIR__.'/public';
        });

        // -- Set  migrations
        \Schema::create('photos', function ($table) {
            $table->increments('id');
            $table->string('filename');
        });

        Photo::create(['id' => 1, 'filename' => 'image1.jpg']);
        Photo::create(['id' => 2, 'filename' => 'invalid.txt.jpg']);
        Photo::create(['id' => 3, 'filename' => 'subfolder/image3.jpg']);
    }

    public function _tearDown() {
        \Schema::drop('photos');
        parent::teadDown();
    }

    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function test_Setup() {
        foreach (Photo::all() as $image) {
            $this->assertFileExists($image->absolutePath());
        }
    }

    public function test_Decoratable() {
        $image = Photo::find(3);
        $this->assertInstanceOf(Photo::class, $image);
        $version = $image->version(v200x200::class);

        $this->assertInstanceOf(Version::class, $version);
        $this->assertEquals('v200x200', $version->versionName());
    }

    public function test_Paths() {
        $this->assertEquals('v200x200/image1.jpg',              Photo::find(1)->version(v200x200::class)->relativePath());
        $this->assertEquals('subfolder/v200x200/image3.jpg',    Photo::find(3)->version(v200x200::class)->relativePath());

        $this->assertEquals('/v200x200/image1.jpg',              Photo::find(1)->version(v200x200::class)->url());
        $this->assertEquals('/subfolder/v200x200/image3.jpg',    Photo::find(3)->version(v200x200::class)->url());
    }

    public function test_Create_Folders() {
        \File::deleteDirectory(public_path('subfolder/v200x200'));
        \File::deleteDirectory(public_path('v200x200'));

        $this->assertFileNotExists(public_path('v200x200'));
        $this->assertFileNotExists(public_path('subfolder/v200x200'));

        Photo::find(1)->version(v200x200::class);
        Photo::find(3)->version(v200x200::class);

        $this->assertFileExists(public_path('v200x200'));
        $this->assertFileExists(public_path('subfolder/v200x200'));
    }

    public function test_Create_Files() {
        \File::deleteDirectory(public_path('subfolder/v200x200'));
        \File::deleteDirectory(public_path('v200x200'));

        $image1 = Photo::find(1)->version(v200x200::class);
        $image3 = Photo::find(3)->version(v200x200::class);

        $this->assertFileExists($image1->absolutePath());
        $this->assertFileExists($image3->absolutePath());
    }

    public function test_Invalid_Image() {
        $this->setExpectedException(Exception::class);
        Photo::find(2)->version(v200x200::class);
    }

    public function test_Namespacing() {
        $photo = Photo::find(1);
        $version = $photo->version(v200x200::class);
        $this->assertEquals('igaster\imageVersions\Tests\App\Transformations\v200x200', $version->className());

        $photo->transformationNamespace = 'igaster\imageVersions\Tests\App\Transformations';
        $version = $photo->version('v200x200');
        $this->assertEquals('igaster\imageVersions\Tests\App\Transformations\v200x200', $version->className());
    }

    public function test_Invalid_Transformation() {
        $this->setExpectedException(igaster\imageVersions\Exceptions\TransformationNotFound::class);
        Photo::find(1)->version('invalidTransformation');
    }

    public function test_Parameters() {
        \File::deleteDirectory(public_path('vParameters'));
        $this->expectOutputString('1,2,3');
        Photo::find(1)->version(vParameters::class,1,2,3);
    }

    public function test_Parameters_Default_Value() {
        \File::deleteDirectory(public_path('vParameters'));
        $this->expectOutputString('1,2,99');
        Photo::find(1)->version(vParameters::class,1,2);
    }

    public function test_Retreive_Saved_File_Instead_Of_Creating_New() {
        \File::deleteDirectory(public_path('vParameters'));
        Photo::find(1)->version(vParameters::class);
        $this->expectOutputString('');
        Photo::find(1)->version(vParameters::class, 'Image Created!');
    }

    public function test_missing_apply_method() {
        $this->setExpectedException(igaster\imageVersions\Exceptions\missingApplyMethod::class);
        Photo::find(1)->version(vMissingMethod::class);
    }

}