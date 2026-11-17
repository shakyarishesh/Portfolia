<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uploaded files: avatar, resume, project thumbnails and screenshots.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('model');
            $table->string('collection', 40)->index();
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30); // claude_export | claude_code | text
            $table->string('original_name')->nullable();
            $table->string('status', 20)->default('parsed');
            $table->unsignedInteger('conversation_count')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->json('extractors')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('imported_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_import_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->longText('transcript');
            $table->timestamps();
        });

        Schema::create('proposed_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('imported_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action', 10); // create | update
            $table->json('proposed');
            $table->json('current')->nullable();
            $table->string('classification', 20); // new | changed | duplicate | contradiction
            $table->string('extractor', 20); // keyword | llm | manual
            $table->decimal('confidence', 3, 2)->nullable();
            $table->text('source_excerpt')->nullable();
            $table->text('rationale')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'target_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 20); // created | updated | deleted
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('source', 20)->default('admin'); // admin | import | restore | seed | console
            $table->foreignId('proposed_change_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'proposed_changes', 'imported_conversations', 'conversation_imports', 'contact_messages', 'media'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
