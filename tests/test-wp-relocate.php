<?php

/**
 * WP_Relocate test case.
 */
class WP_RelocateTest extends WP_UnitTestCase {

	/**
	 *
	 * @var WP_Relocate
	 */
	private $WP_Relocate;

	private $original;

	private $destination;

	/**
	 * Prepares the environment before running a test.
	 */
	function setUp () {
		parent::setUp();
		
		$this->original = 'http://' . WP_TESTS_DOMAIN;
		$this->destination = 'http://wp.example.com/wordpress';
		$this->WP_Relocate = new WP_Relocate( $this->original, 
				$this->destination );
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	function tearDown () {
		$this->WP_Relocate = null;
		
		parent::tearDown();
	}

	/**
	 * Constructs the test case.
	 */
	public function __construct () {
		// TODO Auto-generated constructor
	}

	/**
	 * Tests WP_Relocate->__construct()
	 */
	public function test___construct () {
		$this->setExpectedException( 'InvalidArgumentException' );
		new WP_Relocate( '1234', $this->destination );
		new WP_Relocate( $this->original, '1234' );
	}

	/**
	 * Tests WP_Relocate->replace_post_content()
	 */
	public function test_replace_post_content () {
		// blank table, should have no updates
		$this->assertCount( 0, $this->WP_Relocate->replace_post_content() );
		
		// factory manufacture a post containing at least one instance of the
		// old URL
		$post_id = $this->factory->post->create( 
				array( 
						'post_status' => 'publish',
						'post_content' => 'Lorem ipsum <a href="' .
								 $this->original .
								 '/wp-content/uploads/asdf.png">' .
								 $this->original . '</a>.' 
				) );
		// replacement should affect the post
		$this->assertCount( 1, $this->WP_Relocate->replace_post_content() );
		$this->assertInstanceOf( 'WP_Post', $post = get_post( $post_id ) );
		$this->assertContains( $this->destination, $post->post_content );
		$this->assertNotContains( $this->original, $post->post_content );
		
		// a revision containing the previous data should exist
		$post_revisions = wp_get_post_revisions( $post_id );
		require ABSPATH . '/wp-includes/version.php';
		if ( version_compare( 
				substr( $wp_version, 0, strpos( $wp_version, '-' ) ), '3.6', 
				'>=' ) ) {
			// I really shouldn't have to do this
			$this->assertCount( 2, $post_revisions );
			// first revision is replaced
			$this->assertContains( $this->destination, 
					current( $post_revisions )->post_content );
			$this->assertNotContains( $this->original, 
					current( $post_revisions )->post_content );
			next( $post_revisions ); // next revision is original
			$this->assertContains( $this->original, 
					current( $post_revisions )->post_content );
			$this->assertNotContains( $this->destination, 
					current( $post_revisions )->post_content );
		} else {
			$this->assertCount( 1, $post_revisions );
			// this revision is the old (preserved) content
			$this->assertContains( $this->original, 
					current( $post_revisions )->post_content );
			$this->assertNotContains( $this->destination, 
					current( $post_revisions )->post_content );
		}
		
		// factory manufacture a post not containing the old URL
		$rand_str = rand_str();
		$post_id_unaffected = $this->factory->post->create( 
				array( 
						'post_status' => 'publish',
						'post_content' => $rand_str 
				) );
		// check that what we do doesn't touch it
		$this->assertCount( 1, 
				$this->WP_Relocate->replace_post_content( 
						array( 
								$post_id_unaffected 
						) ) );
		$this->assertEquals( get_post( $post_id_unaffected )->post_content, 
				$rand_str );
	}

	/**
	 * Tests WP_Relocate->replace_attachments()
	 */
	public function test_replace_attachments () {
		// no attachments, thus should return empty array
		$this->assertCount( 0, $this->WP_Relocate->replace_attachments() );
		
		// from Tests_Posts_Attachments
		require getenv( 'WP_TESTS_DIR' ) . '/tests/post/attachments.php';
		$Tests_Post_Attachments = new Tests_Post_Attachments();
		// this image is smaller than the thumbnail size so it won't have one
		$filename = (DIR_TESTDATA . '/images/test-image.jpg');
		$contents = file_get_contents( $filename );
		
		$upload = wp_upload_bits( basename( $filename ), null, $contents );
		$this->assertTrue( empty( $upload['error'] ) );
		$id = $Tests_Post_Attachments->_make_attachment( $upload );
		$id_another = $Tests_Post_Attachments->_make_attachment( $upload );
		
		// test replacement on a given attachment
		$replace_this_one = $this->WP_Relocate->replace_attachments( 
				array( 
						$id 
				) );
		$this->assertCount( 1, $replace_this_one );
		$this->assertArrayHasKey( $id, $replace_this_one );
		
		// on non-existent attachment ID
		$replace_nonexistent = $this->WP_Relocate->replace_attachments( 
				array( 
						- 1,
						- 2 
				) );
		$this->assertCount( 0, $replace_nonexistent );
		$this->assertArrayNotHasKey( $id, $replace_nonexistent );
		
		// global attachment replace should include ALL the files we uploaded
		$replace_global = $this->WP_Relocate->replace_attachments();
		$this->assertArrayHasKey( $id, $replace_global );
		$this->assertArrayHasKey( $id_another, $replace_global );
		$this->assertContains( $this->destination, get_post( $id )->guid );
		
		/*
		 * Since wp_upload_dir() depends on the constant WP_CONTENT_URL, it is
		 * literally impossible to test that attachment URLs have changed until
		 * the next time the program runs. For our purposes, we can only test by
		 * setting an (optional) option, upload_url_path, to the old value that
		 * WordPress calculated on its own, then test a global options replace.
		 */
		$this->assertTrue( 
				update_option( 'upload_url_path', wp_upload_dir()['baseurl'] ) );
		$this->assertArrayHasKey( 'upload_url_path', 
				$this->WP_Relocate->replace_options() );
		$this->assertContains( $this->destination, 
				get_option( 'upload_url_path' ) );
		$this->assertContains( $this->destination, 
				wp_get_attachment_url( $id ) );
	}

	/**
	 * Tests WP_Relocate->replace_options()
	 */
	public function test_replace_options () {
		// siteurl starts off normal
		$this->assertEquals( get_option( 'siteurl' ), $this->original );
		
		// replace only one option, others untouched
		$replace_one = $this->WP_Relocate->replace_options( 
				array( 
						'home' 
				) );
		$this->assertEquals( count( $replace_one ), 1 );
		$this->assertEquals( get_option( 'home' ), $this->destination );
		$this->assertEquals( get_option( 'siteurl' ), $this->original );
		
		// serialize something for later use
		$serialized_option = array( 
				array( 
						$this->original . '/wp-admin and ' . $this->original .
								 ' multiple times in one string' 
				) 
		);
		$serialized_option_expected = array( 
				array( 
						$this->destination . '/wp-admin and ' .
								 $this->destination .
								 ' multiple times in one string' 
				) 
		);
		
		$this->assertTrue( 
				add_option( 'relocate_serialized_option', $serialized_option ) );
		
		// replace everything in the options table
		$replace_all = $this->WP_Relocate->replace_options();
		$this->assertEquals( get_option( 'siteurl' ), $this->destination );
		// check that replace-all included the serialized option
		$this->assertEquals( 
				$serialized_option_get = get_option( 
						'relocate_serialized_option' ), 
				$serialized_option_expected );
		// check that serialized option is singly-serialized
		$this->assertFalse( is_serialized( $serialized_option_get ) );
	}

	/**
	 * Tests WP_Relocate->_replace()
	 */
	public function test__replace () {
		// string test
		$string = 'Some prefix ' . $this->original .
				 '/wp-content/themes/twentytwelve/style.css';
		$expected = 'Some prefix ' . $this->destination .
				 '/wp-content/themes/twentytwelve/style.css';
		
		$this->assertEquals( $expected, 
				$this->WP_Relocate->_replace( $string ) );
		
		// array test
		$array = array( 
				1 => 'test',
				2 => 'Some prefix ' . $this->original .
						 '/wp-content/themes/twentytwelve/style.css',
						'key ' . $this->original => 'value ' . $this->original 
		);
		$expected = array( 
				1 => 'test',
				2 => 'Some prefix ' . $this->destination .
						 '/wp-content/themes/twentytwelve/style.css',
						'key ' . $this->original => 'value ' . $this->destination 
		);
		
		$this->assertEquals( $expected, $this->WP_Relocate->_replace( $array ) );
		
		// object & serialized object test
		$object = $this->getMock( 'MockObject' );
		$object->an_array = array( 
				'an_option' => $this->original . '/my-site',
				'another' => substr( $this->original, 0, 
						strlen( $this->original - 1 ) ),
				'an_array' => array( 
						$this->original . '/my-site' 
				) 
		);
		$object->a_string = $this->original . '/what';
		$serialized_object = serialize( $object );
		
		$expected = clone $object;
		$expected->an_array = array( 
				'an_option' => $this->destination . '/my-site',
				'another' => substr( $this->original, 0, 
						strlen( $this->original - 1 ) ),
				'an_array' => array( 
						$this->destination . '/my-site' 
				) 
		);
		$expected->a_string = $this->destination . '/what';
		$serialized_expected = serialize( $expected );
		
		$this->assertEquals( $expected, 
				$this->WP_Relocate->_replace( $object ) );
		
		$serialized_result = $this->WP_Relocate->_replace( $serialized_object );
		
		$this->assertEquals( $serialized_expected, $serialized_result );
	}
	
	public function test_is_valid_siteurl() {
	    $url = 'http://example.com/old';
	    $this->assertTrue(WP_Relocate::is_valid_siteurl($url));
	    
	    $url = 'http://example.com/dir/?';
	    $this->assertFalse(WP_Relocate::is_valid_siteurl($url));
	}
}

