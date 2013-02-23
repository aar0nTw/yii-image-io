<?php
/**
 * Ext Image Service
 * @name EImageIOProxy
 * @author Aaron Huang
 * @license www.opensource.org/licenses/mit-license.php
 *
 */

class EImageIOProxy extends CApplicationComponent
{
	public $filePath;
	public $configPath;
	public $errorImg;
	public $localCache = false;
	public $remoteMode = false;

	public function init()
	{
		parent::init();
		if(is_null($this->configPath))
		{
			$this->configPath = dirname(__FILE__).'/config/imageServiceConfig.xml';
		}

		if(is_null($this->errorImg))
		{
			$this->errorImg = dirname(__FILE__).'/errorImg/error.png';
		}
	}

	public function getImage($pid=NULL,$configName=NULL,$width=null,$height=null,$return=false)
	{
		if($this->localCache && array_key_exists("HTTP_IF_MODIFIED_SINCE",$_SERVER))
		{
			header("HTTP/1.0 304 Not Modified",true);
			exit();
		}

		if(isset($_REQUEST['pid']))
			$pid = $_REQUEST['pid'];

		if(isset($_REQUEST['configName']))
			$configName = $_REQUEST['configName'];

		if(isset($_REQUEST['width']))
			$width = $_REQUEST['width'];

		if(isset($_REQUEST['height']))
			$height = $_REQUEST['height'];

		$imageName = $pid;
		if(!$imageName && !$configName && !$width && !$height)
	 		throw new CHttpException(404,'圖片服務:錯誤的圖片名稱');

		$start_x = null;
		$start_y = null;
		$dst_w = null;
		$dst_h = null;

		if(sizeof($cropprefix = explode('_', $imageName))==3)
		{
			list($start_x,$start_y,$imageName) = $cropprefix;
		}elseif(sizeof($cropprefix)==5){
			list($start_x,$start_y,$dst_w,$dst_h,$imageName) = $cropprefix;
		}

		$filenameArray = explode('.',$imageName);
		$filenameExt = $filenameArray[sizeof($filenameArray)-1];
		$filename = $this->filePath.$imageName;
		$noExtName = $this->filePath.$filenameArray[sizeof($filenameArray)-2];
		$noimageflag = false;
		$jpegflag = 1;
		if($filenameExt == 'png' or $filenameExt == 'PNG')
			$jpegflag = 0;

		if($this->file_exist($filename) && !is_null($pid))
		{
			$tempimg = $image = $this->processFileWithType($filename);
		}else{
			$tempimg = $image = $this->noImage();
			$noimageflag = true;
		}

		if($configName && $config = $this->loadImgConfig($configName))
		{
			//Crop image.
			if((!$noimageflag) && ($start_x || $start_x==='0') && ($start_y || $start_y==='0'))
			{
				$image = $this->crop($image, $start_x, $start_y, ($dst_w)?$dst_w:$config['width'], ($dst_h)?$dst_h:$config['height']);
			}
			//Crop image end.

			$src_w = imagesx($image);
			$src_h = imagesy($image);

			if($ration = ($config['ratio']==1)?$src_w < $src_h:$src_w > $src_h){
				$temp_w = $config['width'];
				$temp_h = intval($src_h / $src_w * $config['width']);
			}else{
				if($src_w == $src_h){
					if($config['width']>$config['height']){
						$temp_h = $config['width'];
						$temp_w = intval($src_w / $src_h * $config['width']);
					}else{
						$temp_h = $config['height'];
						$temp_w = intval($src_w / $src_h * $config['height']);
					}
				}else{
					$temp_h = $config['height'];
					$temp_w = intval($src_w / $src_h * $config['height']);
				}
			}


			if($config['ratio'] == 1){
				$tempimg =  imagecreatetruecolor($config['width'],$config['height']);
				imagefill($tempimg,0,0,imagecolorallocate($tempimg,'0x'.$config['bgcolor'][0],'0x'.$config['bgcolor'][1],'0x'.$config['bgcolor'][2]));
				$mx = round(($config['width']-$temp_w)/2);
				$my = round(($config['height']-$temp_h)/2);
			}else{
				$tempimg =  imagecreatetruecolor($temp_w,$temp_h);
			}
			imagecopyresampled($tempimg, $image, ($mx)?$mx:0, ($my)?$my:0, 0, 0, $temp_w, $temp_h, $src_w, $src_h);
		}

		if(!is_null($width) && !is_null($height)){
				//Crop image.
				if((!$noimageflag) && $start_x && $start_y)
				{
					$tempimg = $this->crop($image, $start_x, $start_y, $width, $height);
				}else{
					$src_w = imagesx($image);
					$src_h = imagesy($image);
					if(($src_w > $src_h and $width !=0)  || $height == 0){
						$temp_w = $width;
						$temp_h = intval($src_h / $src_w * $width);
					}elseif(($src_w < $src_h and $height != 0) || $width == 0){
						$temp_h = $height;
						$temp_w = intval($src_w / $src_h * $height);
						//return;
					}else{
						$temp_w = $width;
						$temp_h = intval($src_h / $src_w * $width);
					}
					$tempimg =  imagecreatetruecolor($temp_w,$temp_h);
					imagecopyresampled($tempimg, $image, 0, 0, 0, 0, $temp_w, $temp_h, $src_w, $src_h);
				}
		}
		if($return)
			return $tempimg;
		header("Cache-Control: max-age=315360000",true);
		header("Last-Modified:never",true);
		if($jpegflag){
			header("Content-type: image/JPEG",true);
			imagejpeg($tempimg,null,98);
		}else{
			header("Content-type: image/PNG",true);
			imagepng($tempimg);
		}
		imagedestroy($tempimg);
	}

	/**
	 * @name LoadImgConfig
	 * @todo load {config}.xml in IMG_CONFIG_PATH
	 * @param string $config
	 * @return array($width,$hight)
	 */
	protected function loadImgConfig($conf_name="normal")
	{
		$key = 'imgconfig_'.$conf_name;
		if(extension_loaded('apc') && $confCache = Yii::app()->apc->get($key)){
			return $confCache;
		}else{
			$doc = new DOMDocument();
			$doc->load($this->configPath);
			$configs = $doc->getElementsByTagName("config");
			foreach ($configs as $config)
			{
				$names = $config->getElementsByTagName("name")->item(0);
				$name = $names->nodeValue;
				if($name != $conf_name)
				{
					continue;
				}else{
					$w = $config->getElementsByTagName("width")->item(0);
					$h = $config->getElementsByTagName("height")->item(0);
					$r = $config->getElementsByTagName("ratio")->item(0);
					$bg = $config->getElementsByTagName("bgcolor")->item(0);
					$width = $w->nodeValue;
					$height = $h->nodeValue;
					$ratio = $r->nodeValue;
					$bgcolor = $this->parseColor($bg->nodeValue);

					$result = array('width'=>$width,'height'=>$height,'ratio'=>$ratio,'bgcolor'=>$bgcolor);
					if(extension_loaded('apc')){
						Yii::app()->apc->set($key,$result,1800);
					}
					return $result;
				}
			}
		}
		return false;
	}

	private function processFileWithType($filename)
	{
		$image = null;
		if($this->file_exist($filename))
		{
			list($imagewidth, $imageheight, $imageType) = getimagesize($filename);
			$imageType = image_type_to_mime_type($imageType);
			switch ($imageType) {
			    case "image/pjpeg":
				case "image/jpeg":
				case "image/jpg":
					$image=imagecreatefromjpeg($filename);
					break;
			    case "image/png":
				case "image/x-png":
					$image=imagecreatefrompng($filename);
					$jpegflag=0;
					break;
			}
			$image;
		}else{
			$image = $this->noImage();
		}
		return $image;
	}

	protected function noImage()
	{
		return $image = $this->processFileWithType($this->errorImg);
	}

	private function file_exist($filename)
	{
		$existsFlag = false;
		if($this->remoteMode)
			$existsFlag = @fopen($filename,'r');
		else
			$existsFlag = file_exists($filename);
		return $existsFlag;
	}

	private function parseColor($color)
	{
		$tmp = array();
		array_push($tmp,substr($color,0,2));
		array_push($tmp,substr($color,2,2));
		array_push($tmp,substr($color,4,2));
		return $tmp;
	}

	private function crop($image,$start_x,$start_y,$w,$h)
	{
		$t_image = imagecreatetruecolor($w,$h);
		imagecopyresampled($t_image, $image, 0, 0, $start_x, $start_y, $w, $h, $w, $h);
		return $image = $t_image;
	}
}

?>
