<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('comments.table_prefix', '');

        Schema::create($prefix.'comments', function (Blueprint $table) {
            $table->id();
            // morphs() follows Schema::morphUsingUuids() / morphUsingUlids().
            $table->morphs('commentable');
            $table->morphs('author');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            // The top-level comment of the thread, so a page of threads
            // loads with one query however deep the replies go.
            $table->unsignedBigInteger('root_id')->nullable()->index();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->text('body');
            $table->text('html');
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($prefix.'comment_reactions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('comment_id')->constrained($prefix.'comments')->cascadeOnDelete();
            $table->morphs('reactor');
            $table->string('emoji', 32);
            $table->timestamp('created_at')->nullable();
            $table->unique(['comment_id', 'reactor_type', 'reactor_id', 'emoji'], $prefix.'comment_reactions_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('comments.table_prefix', '');

        Schema::dropIfExists($prefix.'comment_reactions');
        Schema::dropIfExists($prefix.'comments');
    }
};
