-- Inserting initial user: test1@test.com | admin
INSERT INTO `user` (`id`, `email`, `roles`, `password`)
VALUES (NULL, 'test1@test.com', '[\"ROLE_ADMIN\", \"ROLE_USER\"]', '$2y$13$43m.eXA02WnQoboqcu3yieANk.SI4DYSot9y62wt9lCuZXtXcn3bq')