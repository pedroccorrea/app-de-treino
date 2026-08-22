# Schema Formal do Banco de Dados (SQLite)
## Tabela: users
- id: unsigned big integer, primary key, auto increment
- name: string, not null
- email: string, unique, not null
- password: string, not null
- timestamps
## Tabela: exercises
- id: unsigned big integer, primary key, auto increment
- user_id: unsigned big integer, nullable, foreign key references users(id) on delete cascade
- name: string, not null
- primary_muscle: string (MuscleGroup enum value), not null
- secondary_muscles: json, nullable
- equipment_type: string, nullable
- timestamps
## Tabela: workouts
- id: unsigned big integer, primary key, auto increment
- user_id: unsigned big integer, foreign key references users(id) on delete cascade
- name: string, not null
- description: text, nullable
- days_of_week: json, nullable (array de inteiros representando DayOfWeek: 1 a 7)
- timestamps
## Tabela: workout_exercises
- id: unsigned big integer, primary key, auto increment
- workout_id: unsigned big integer, foreign key references workouts(id) on delete cascade
- exercise_id: unsigned big integer, foreign key references exercises(id) on delete cascade
- order: integer, default 0, not null
- target_sets: integer, default 3, not null
- target_reps: integer, default 10, not null
- rest_seconds: integer, default 60, not null
- notes: text, nullable
- timestamps
## Tabela: workout_sessions
- id: unsigned big integer, primary key, auto increment
- user_id: unsigned big integer, foreign key references users(id) on delete cascade
- workout_id: unsigned big integer, nullable, foreign key references workouts(id) on delete set null
- started_at: datetime, not null
- completed_at: datetime, nullable
- notes: text, nullable
- timestamps
## Tabela: set_logs
- id: unsigned big integer, primary key, auto increment
- workout_session_id: unsigned big integer, foreign key references workout_sessions(id) on delete cascade
- exercise_id: unsigned big integer, foreign key references exercises(id) on delete cascade
- set_number: integer, not null
- weight: decimal(8,2), not null (em kg)
- reps: integer, not null
- rpe: integer, nullable (1 a 10)
- timestamps
- Índice único composto: [workout_session_id, exercise_id, set_number]
