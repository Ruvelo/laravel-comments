<!doctype html>
<html><head><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $post->title }}</title></head>
<body><h1>{{ $post->title }}</h1><x-comments::thread :for="$post" /></body></html>
