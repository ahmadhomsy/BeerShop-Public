FROM python:3.12-slim

WORKDIR /app

# Install build dependencies for psycopg2 and tools
RUN apt-get update && apt-get install -y \
    gcc \
    libpq-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt /app/
RUN pip install --no-cache-dir -r requirements.txt

COPY . /app/

EXPOSE 50051

CMD ["python", "server.py"]
