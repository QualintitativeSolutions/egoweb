function download(url, studyId, type, file){
var b = new FileManager();
b.download_file(url,'egowebaudio/' + studyId + '/' + type + '/', file, loadedAudioFiles++);
/*
	window.requestFileSystem(
		LocalFileSystem.TEMPORARY, 0,
		function onFileSystemSuccess(entry) {
			entry.root.getDirectory(
				"egowebaudio",
				{create: true, exclusive: false},
				function onGetDirectorySuccess(fileSystem){
					entry.root.getFile(
						"egowebaudio/29076.mp3",
						{create: true, exclusive: false},
						function gotFileEntry(fileEntry){
							var sPath = fileEntry.toNativeURL();
							var fileTransfer = new FileTransfer();
							fileEntry.remove();
							fileTransfer.onprogress = function(progressEvent) {
								if (progressEvent.lengthComputable) {
									$("#ready").html(progressEvent.loaded / progressEvent.total * 100);
								}
							};
							fileTransfer.download(
								encodeURI("http://rand.bluscs.com/audio/39/EGO/29076.mp3"),
								sPath,
								function (theFile) {
									alert("download complete: " + theFile.toNativeURL());
									$("#ready").html(theFile.toNativeURL());
								},
								function (error) {
									console.log("download error source " + error.source);
									console.log("download error target " + error.target);
									console.log("upload error code: " + error.code);
								}
							);
						},
						fail
					);
				},
				fail
			);
		},
		fail
	);
	*/
}