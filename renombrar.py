import os
import re
from pathlib import Path

# 1. Define la ruta de la carpeta donde están las portadas.
# Cambia esto por la ruta real de tu carpeta storage/app/public/portadas
DIRECTORIO = r"C:\Users\Josue\Desktop\sakura_bookstore\storage\portadas"

def renombrar_imagenes(directorio):
    ruta_dir = Path(directorio)
    
    if not ruta_dir.exists():
        print(f"Error: La ruta '{directorio}' no existe.")
        return

    archivos_procesados = 0
    archivos_renombrados = 0

    for archivo in ruta_dir.iterdir():
        if archivo.is_file():
            archivos_procesados += 1
            nombre_original = archivo.name
            
            # Buscar el patrón: números seguidos de un guion bajo (ej: "25_9781234567890.jpg")
            coincidencia = re.match(r"^(\d+)_", nombre_original)
            
            if coincidencia:
                id_producto = coincidencia.group(1)
                extension = archivo.suffix # Mantiene .jpg, .png, etc.
                
                nuevo_nombre = f"{id_producto}{extension}"
                nueva_ruta = ruta_dir / nuevo_nombre
                
                # Evitar sobrescribir si por alguna razón el archivo {id}.jpg ya existe
                if not nueva_ruta.exists():
                    archivo.rename(nueva_ruta)
                    print(f"Renombrado: {nombre_original} -> {nuevo_nombre}")
                    archivos_renombrados += 1
                else:
                    print(f"Omitido: {nuevo_nombre} ya existe. No se renombró {nombre_original}")
            else:
                print(f"Ignorado: {nombre_original} (no coincide con el formato esperado)")

    print("-" * 30)
    print(f"Proceso terminado. Se procesaron {archivos_procesados} archivos y se renombraron {archivos_renombrados}.")

if __name__ == "__main__":
    renombrar_imagenes(DIRECTORIO)