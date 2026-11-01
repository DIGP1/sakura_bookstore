import os
import time
import urllib.parse
import pandas as pd
import requests

# 1. Cargar el archivo CSV
archivo_csv = 'data-1792273661664.csv'
df = pd.read_csv(archivo_csv)

carpeta_destino = 'portadas_libros'
os.makedirs(carpeta_destino, exist_ok=True)


def guardar_imagen(contenido, id_producto, isbn):
  # Formato estándar y limpio: ID_ISBN.jpg (Ejemplo: 1_9781588139986.jpg)
  nombre_archivo = f'{id_producto}_{isbn}.jpg'
  ruta = os.path.join(carpeta_destino, nombre_archivo)

  with open(ruta, 'wb') as f:
    f.write(contenido)


def descargar_hibrido(nombre_libro, id_producto, isbn):
  # 1. Buscar en Google Books por título
  try:
    query = urllib.parse.quote(f'intitle:{nombre_libro}')
    url_gb = f'https://www.googleapis.com/books/v1/volumes?q={query}&langRestrict=es&maxResults=1'
    res = requests.get(url_gb, timeout=8).json()

    if 'items' in res and len(res['items']) > 0:
      img_url = (
          res['items'][0]
          .get('volumeInfo', {})
          .get('imageLinks', {})
          .get('thumbnail')
      )
      if img_url:
        img_res = requests.get(img_url.replace('http://', 'https://'))
        if img_res.status_code == 200:
          guardar_imagen(img_res.content, id_producto, isbn)
          print(f'[✓] Google Books: {nombre_libro} -> {id_producto}_{isbn}.jpg')
          return
  except Exception:
    pass

  # 2. Buscar en Open Library por título (Fallback)
  try:
    url_ol = f'https://openlibrary.org/search.json?q={urllib.parse.quote(nombre_libro)}'
    res = requests.get(url_ol, timeout=8).json()
    docs = res.get('docs', [])
    for doc in docs:
      cover_i = doc.get('cover_i')
      if cover_i:
        img_res = requests.get(
            f'https://covers.openlibrary.org/b/id/{cover_i}-L.jpg'
        )
        if img_res.status_code == 200 and len(img_res.content) > 1000:
          guardar_imagen(img_res.content, id_producto, isbn)
          print(f'[✓] Open Library: {nombre_libro} -> {id_producto}_{isbn}.jpg')
          return
  except Exception:
    pass

  print(f'[!] No se encontró portada: {nombre_libro}')


print('Iniciando descarga de portadas...\n')

for _, fila in df.iterrows():
  nombre = str(fila['nombre']).strip()
  id_producto = fila['id_producto']
  isbn = str(fila['isbn']).strip()

  descargar_hibrido(nombre, id_producto, isbn)
  time.sleep(0.3)

print('\n¡Proceso finalizado!')