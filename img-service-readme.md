#Nexdoor Image Service Component
###Version: r4485
###Release Date: 11/11/15
---
###Take the tour:

1. 將ImageServcie.tar.gz 解壓縮至專案目錄下
2. 修改 config/main.php:

		'components'=>array(
			..略
			//加入以下內容
			'imgService'=>array(
				'class'=>'CImageService',
				'filePath'=>'檔案存放的位置',
				'configPath'=>'設定檔存放位置',	
				'errorImg'=>'圖片不存在時所出現的預設圖片',
			)
		);
3. main config參數定義
	* filePath : 圖片資料夾位置，必填
	* configPath : 設定檔存放位置，非必填，預設為protected/config/imageServiceConfig.xml
	* errorImg : 圖片不存在時所出現之預設圖片，非必填，預設為protected/components/errorImg/error.png
4. 在 action 中呼叫 ImageService:

		Yii::app()->imgService->getImage();
5. Request 參數定義
	* $_REQUEST['pid'] : 圖片檔名 __*Require__
	* $_REQUEST['configName'] : 設定名
	* $_REQUEST['width'] : 寬度
	* $_REQUEST['height'] : 長度