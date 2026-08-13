# Guía de Trabajo en Equipo - Teatro

## 🚀 Configuración Inicial

### Primera vez - Clonar el proyecto

```bash
# Clona el repositorio
git clone https://github.com/DesarrolloMayaton/teatro.git

# Entra a la carpeta
cd teatro

# Cambia a tu rama personal
git checkout [tu-rama]
```

### Asignación de ramas por persona:
- **Moisés Ávila** → `git checkout moises-avila`
- **Moisés Salvador** → `git checkout MOIXKAR`
- **Hugo** → `git checkout hugo`
- **Ulises** → `git checkout ulises`
- **Philip** → `git checkout philip`
- **Posyo** → `git checkout posyo`

## 🌿 Estructura de Ramas

El proyecto tiene las siguientes ramas:

- **main**: Rama principal con el código más actualizado del proyecto
- **moises-avila**: Rama personal de Moisés Ávila
- **MOIXKAR**: Rama personal de Moisés Salvador
- **hugo**: Rama personal de Hugo
- **ulises**: Rama personal de Ulises
- **philip**: Rama personal de Philip
- **posyo**: Rama personal de Posyo

## 📋 Flujo de Trabajo

### 1. Comenzar a trabajar

Antes de empezar cualquier tarea, asegúrate de tener los últimos cambios:

```bash
# Cambia a tu rama personal
git checkout [tu-rama]

# Actualiza desde main
git p
```

### 2. Trabajar en tu rama

Haz todos tus cambios en tu rama personal:

```bash
# Verifica en qué rama estás
git branch

# Haz tus cambios y guárdalos
git add .
git commit -m "Descripción clara de los cambios"
```

### 3. Subir tus cambios

```bash
# Sube tu rama al repositorio remoto
git push origin [tu-rama]
```

### 4. Integrar tus cambios a main

Cuando termines una funcionalidad y esté lista para compartir:

**Opción A: Pull Request (Recomendado)**
1. Ve a GitHub
2. Crea un Pull Request desde tu rama hacia `main`
3. Espera la revisión del equipo
4. Una vez aprobado, haz merge

**Opción B: Merge directo**
```bash
# Cambia a main
git checkout main

# Trae los últimos cambios
git pull origin main

# Integra tu rama
git merge [tu-rama]

# Sube los cambios
git push origin main
```

## 🚀 Subir Cambios a Main

### Proceso para llevar tu código a main:

**Cualquier miembro del equipo puede hacer esto cuando termine su trabajo:**

```bash
# 1. Asegúrate de estar en tu rama y tener todo guardado
git checkout [tu-rama]
git add .
git commit -m "Descripción de los cambios"

# 2. Actualiza tu rama con los últimos cambios de main
git pull origin main

# 3. Resuelve conflictos si los hay, luego:
git add .
git commit -m "Resuelve conflictos con main"

# 4. Cambia a main
git checkout main
git pull origin main

# 5. Integra tu rama en main
git merge [tu-rama]

# 6. Sube los cambios a main
git push origin main

# 7. Regresa a tu rama para seguir trabajando
git checkout [tu-rama]
```

### ⚠️ Antes de subir a main, verificar:
- ✅ Tu código funciona correctamente
- ✅ No hay errores en el código
- ✅ Has probado tus cambios
- ✅ Has actualizado desde main antes del merge
- ✅ Tenga validaciones robustas

## 🔄 Mantener Todo Actualizado

### Para mantener tu rama local actualizada:

**Rutina diaria recomendada:**

```bash
# 1. Ve a tu rama personal
git checkout [tu-rama]

# 2. Actualiza desde main (donde están los cambios del equipo)
git pull origin main

# 3. Si hay conflictos, resuélvelos y haz commit
git add .
git commit -m "Agregar menú administracion con su index apas"
git pull origin main
# 4. Sube tu rama actualizada
git push origin [tu-rama]
```

### Para que tus compañeros tengan todo actualizado:

**Cada compañero debe hacer esto regularmente:**

```bash
# Opción 1: Actualizar solo tu rama
git checkout [tu-rama]
git pull origin main

# Opción 2: Actualizar todas las ramas locales
git fetch --all
git checkout [tu-rama]
git pull origin main
```

### 📅 Rutina recomendada para el equipo:

**Al comenzar el día:**
```bash
git checkout [tu-rama]
git pull origin main
```

**Al terminar el día:**
```bash
git add .
git commit -m "Trabajo del día: [descripción]"
git push origin [tu-rama]
```

**Cuando termines una funcionalidad:**
- Sigue el proceso de "Subir Cambios a Main"
- Avisa al equipo para que actualicen sus ramas desde main

## ⚠️ Reglas Importantes

1. **NUNCA trabajes directamente en `main`** - Solo haz merge desde tu rama personal
2. **Siempre trabaja en tu rama personal** - Evita conflictos con el equipo
3. **Actualiza frecuentemente** - Haz `git pull origin main` antes de empezar
4. **Commits descriptivos** - Usa mensajes claros: "Añade formulario de login"
5. **Comunica** - Avisa al equipo cuando subas cambios importantes a main
6. **Actualiza antes de hacer merge** - Siempre trae los últimos cambios de main antes de integrar

## 🔄 Comandos Útiles

```bash
# Ver en qué rama estás
git branch

# Cambiar de rama
git checkout [nombre-rama]

# Ver el estado de tus cambios
git status

# Ver historial de commits
git log --oneline

# Descartar cambios locales (¡cuidado!)
git checkout -- .

# Ver diferencias antes de commit
git diff

# Renombrar una rama
git branch -m nombre-viejo nombre-nuevo
git push origin nombre-nuevo
git push origin --delete nombre-viejo
```

## 🔗 Información del Repositorio

- **URL del repositorio**: https://github.com/DesarrolloMayaton/teatro.git
- **Organización**: DesarrolloMayaton

## 🚨 Resolver Conflictos

Si hay conflictos al hacer merge:

1. Git te mostrará los archivos en conflicto
2. Abre los archivos y busca las marcas `<<<<<<<`, `=======`, `>>>>>>>`
3. Edita el archivo dejando el código correcto
4. Guarda los cambios:
```bash
git add .
git commit -m "Resuelve conflictos"
```

## �  Contacto

Si tienes dudas, pregunta al equipo antes de hacer cambios importantes en `main`.

---

**¡Buena suerte con el proyecto! 🎭**
