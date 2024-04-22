
class Tetromino:
    def __init__(self, shape, color):
        self.shape = shape
        self.color = color
        self.position = [0, 0]
        self.rotation = 0

    def rotate(self):
        self.rotation = (self.rotation + 1) % len(self.shape)
            
    def move(self, dx, dy):
        self.position[0] += dx
        self.position[1] += dy

    def check_collision(grid, tetromino):
        for y, row in enumerate(tetromino.shape[tetromino.rotation]):
            for x, cell in enumerate(row):
                if cell:
                    try:
                        if grid[y + tetromino.position[1]][x + tetromino.position[0]]:
                            return True
                    except IndexError:
                        return True

        return False