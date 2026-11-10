<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('whatsapp', 25)->nullable();

            // website | web_app | template_custom | other
            $table->string('project_type', 20);
            $table->text('description');
            $table->string('deadline', 100)->nullable();
            $table->string('budget', 100)->nullable();

            // new | contacted | quoted | in_progress | done | rejected
            $table->string('status', 20)->default('new');
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requests');
    }
};
