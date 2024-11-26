@echo off

set target=%1
set whisper_path=%2

IF "%target%"=="cpu" goto cpu
IF "%target%"=="cuda" goto cuda
IF "%target%"=="all" goto cpu

echo Unknown target %target%, should "cpu", "cuda" or "all"
goto commonexit

:cpu
	echo Starting building cpu target...
	cd %whisper_path%
	echo %whisper_path%
	rmdir .\build /s /q
	cmake -S . -B ./build -A x64 -DCMAKE_BUILD_TYPE=Release -DWHISPER_BUILD_TESTS=OFF -DWHISPER_BUILD_EXAMPLES=OFF

	cd ./build
	msbuild ALL_BUILD.vcxproj -t:build -p:configuration=Release -p:platform=x64

	IF NOT "%target%"=="all" goto commonexit
:cuda
	echo Starting building CUDA target...
	cd %whisper_path%
	rmdir .\build /s /q
	cmake -S . -B ./build -A x64 -DWHISPER_CUBLAS=ON -DCMAKE_BUILD_TYPE=Release -DWHISPER_BUILD_TESTS=OFF -DWHISPER_BUILD_EXAMPLES=OFF

	cd ./build
	msbuild ALL_BUILD.vcxproj -t:build -p:configuration=Release -p:platform=x64

:commonexit
