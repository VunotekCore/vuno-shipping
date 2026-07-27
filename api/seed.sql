-- vuno_db_shipping — Seed Data
-- Permisos iniciales: user, role, permission (4 acciones cada uno)

INSERT INTO permission (module, action, label) VALUES
('user', 'read', 'Ver usuarios'),
('user', 'create', 'Crear usuario'),
('user', 'edit', 'Editar usuario'),
('user', 'delete', 'Eliminar usuario'),
('role', 'read', 'Ver roles'),
('role', 'create', 'Crear rol'),
('role', 'edit', 'Editar rol'),
('role', 'delete', 'Eliminar rol'),
('permission', 'read', 'Ver permisos'),
('permission', 'create', 'Crear permiso'),
('permission', 'edit', 'Editar permiso'),
('permission', 'delete', 'Eliminar permiso'),
('company', 'read', 'Ver empresa'),
('company', 'edit', 'Editar empresa'),
('document_sequence', 'read', 'Ver secuencias'),
('document_sequence', 'create', 'Crear secuencia'),
('document_sequence', 'edit', 'Editar secuencia'),
('document_sequence', 'delete', 'Eliminar secuencia'),
('area', 'read', 'Ver áreas'),
('area', 'create', 'Crear área'),
('area', 'edit', 'Editar área'),
('area', 'delete', 'Eliminar área'),
('puesto', 'read', 'Ver puestos'),
('puesto', 'create', 'Crear puesto'),
('puesto', 'edit', 'Editar puesto'),
('puesto', 'delete', 'Eliminar puesto'),
('empleado', 'read', 'Ver empleados'),
('empleado', 'create', 'Crear empleado'),
('empleado', 'edit', 'Editar empleado'),
('empleado', 'delete', 'Eliminar empleado')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Roles iniciales
INSERT INTO role (name, slug) VALUES
('Admin', 'admin'),
('Editor', 'editor'),
('Viewer', 'viewer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Admin: todos los permisos
INSERT INTO role_permission (role_id, permission_id)
SELECT 1, id FROM permission
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Editor: read + edit
INSERT INTO role_permission (role_id, permission_id)
SELECT 2, id FROM permission WHERE action IN ('read', 'edit')
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Viewer: solo read
INSERT INTO role_permission (role_id, permission_id)
SELECT 3, id FROM permission WHERE action = 'read'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Admin user default (debe cambiar contraseña en primer login)
INSERT INTO `user` (email, password, name, role_id, must_change_password) VALUES
('admin@vunotek.com', '$2y$12$iYHofg5UxMEvuhp.Myr8PuysEzxnNxPTuhGPMTW42Jmipx.QvO.FG', 'Admin', 1, 1)
ON DUPLICATE KEY UPDATE email = email;
