<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace Tests\Core\Controller;

use OC\Core\Controller\JsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IRequest;
use Test\TestCase;

class JsControllerTest extends TestCase {

	/** @var IAppData|\PHPUnit_Framework_MockObject_MockObject */
	private $appData;

	/** @var JsController */
	private $controller;

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	public function setUp() {
		parent::setUp();

		$this->appData = $this->createMock(IAppData::class);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')
			->willReturn(1337);

		$this->request = $this->createMock(IRequest::class);

		$this->controller = new JsController(
			'core',
			$this->request,
			$this->appData,
			$timeFactory
		);
	}

	public function testNoCssFolderForApp() {
		$this->appData->method('getFolder')
			->with('myapp')
			->willThrowException(new NotFoundException());

		$result = $this->controller->getJs('file.css', 'myapp');

		$this->assertInstanceOf(NotFoundResponse::class, $result);
	}


	public function testNoCssFile() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->method('getFolder')
			->with('myapp')
			->willReturn($folder);

		$folder->method('getFile')
			->willThrowException(new NotFoundException());

		$result = $this->controller->getJs('file.css', 'myapp');

		$this->assertInstanceOf(NotFoundResponse::class, $result);
	}

	public function testGetFile() {
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData->method('getFolder')
			->with('myapp')
			->willReturn($folder);

		$folder->method('getFile')
			->with('file.js')
			->willReturn($file);

		$expected = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'application/javascript']);
		$expected->cacheFor(86400);
		$expires = new \DateTime();
		$expires->setTimestamp(1337);
		$expires->add(new \DateInterval('PT24H'));
		$expected->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$expected->addHeader('Pragma', 'cache');

		$result = $this->controller->getJs('file.js', 'myapp');
		$this->assertEquals($expected, $result);
	}

	public function testGetGzipFile() {
		$folder = $this->createMock(ISimpleFolder::class);
		$gzipFile = $this->createMock(ISimpleFile::class);
		$this->appData->method('getFolder')
			->with('myapp')
			->willReturn($folder);

		$folder->method('getFile')
			->with('file.js.gz')
			->willReturn($gzipFile);

		$this->request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn('gzip, deflate');

		$expected = new FileDisplayResponse($gzipFile, Http::STATUS_OK, ['Content-Type' => 'application/javascript']);
		$expected->addHeader('Content-Encoding', 'gzip');
		$expected->cacheFor(86400);
		$expires = new \DateTime();
		$expires->setTimestamp(1337);
		$expires->add(new \DateInterval('PT24H'));
		$expected->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$expected->addHeader('Pragma', 'cache');

		$result = $this->controller->getJs('file.js', 'myapp');
		$this->assertEquals($expected, $result);
	}

	public function testGetGzipFileNotFound() {
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$this->appData->method('getFolder')
			->with('myapp')
			->willReturn($folder);

		$folder->method('getFile')
			->will($this->returnCallback(
				function($fileName) use ($file) {
					if ($fileName === 'file.js') {
						return $file;
					}
					throw new NotFoundException();
				})
			);

		$this->request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn('gzip, deflate');

		$expected = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'application/javascript']);
		$expected->cacheFor(86400);
		$expires = new \DateTime();
		$expires->setTimestamp(1337);
		$expires->add(new \DateInterval('PT24H'));
		$expected->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$expected->addHeader('Pragma', 'cache');

		$result = $this->controller->getJs('file.js', 'myapp');
		$this->assertEquals($expected, $result);
	}

}
