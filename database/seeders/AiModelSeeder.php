<?php

namespace Database\Seeders;

use App\Models\AImodel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $aiModels = [
            [
                'name' => 'gpt-4o',
                'description' => 'GPT-4o ("o" for "omni") is a multilingual, multimodal generative pre-trained transformer. It can process and generate text, images and audio.',
                'created_at' => '2025-03-08 08:43:14',
                'updated_at' => '2025-03-09 18:46:52',
                'accepts_developer_messages' => 1,
                'accepts_system_messages' => 0,
                'reasoning_effort' => 0,
                'accepts_audio' => 0,
                'accepts_chat' => 1,
            ],
            [
                'name' => 'gpt-4o-mini',
                'description' => 'A smaller and cheaper version of GPT-4o.',
                'created_at' => '2025-03-08 08:44:24',
                'updated_at' => '2025-03-09 18:46:58',
                'accepts_developer_messages' => 1,
                'accepts_system_messages' => 0,
                'reasoning_effort' => 0,
                'accepts_audio' => 0,
                'accepts_chat' => 1,
            ],
            [
                'name' => 'gpt-4',
                'description' => 'GPT-4 is a multimodal large language model, the predecessor of GPT-4o.',
                'created_at' => '2025-03-08 08:46:08',
                'updated_at' => '2025-03-09 18:47:04',
                'accepts_developer_messages' => 0,
                'accepts_system_messages' => 1,
                'reasoning_effort' => 0,
                'accepts_audio' => 0,
                'accepts_chat' => 1,
            ],
            [
                'name' => 'o1-mini',
                'description' => 'o1 is a reflective generative pre-trained transformer (GPT). It spends time "thinking" before it answers, making it better at complex reasoning tasks, science and programming than GPT-4o.',
                'created_at' => '2025-03-08 08:47:22',
                'updated_at' => '2025-03-08 08:47:22',
                'accepts_developer_messages' => 0,
                'accepts_system_messages' => 0,
                'reasoning_effort' => 0,
                'accepts_audio' => 0,
                'accepts_chat' => 1,
            ],
            [
                'name' => 'whisper-1',
                'description' => 'Whisper is a machine learning model for speech recognition, transcription and translation.',
                'created_at' => '2025-03-08 08:54:01',
                'updated_at' => '2025-03-09 18:53:27',
                'accepts_developer_messages' => 0,
                'accepts_system_messages' => 0,
                'reasoning_effort' => 0,
                'accepts_audio' => 1,
                'accepts_chat' => 0,
            ],
        ];

        foreach ($aiModels as $model) {
            AImodel::create($model);
        }
    }
}
